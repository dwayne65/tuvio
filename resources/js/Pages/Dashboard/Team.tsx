import { Avatar, Panel } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Field, Input, Select } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Mail, RotateCw, ShieldCheck, Trash2, UserPlus } from 'lucide-react';
import { FormEvent } from 'react';

type RoleValue = 'owner' | 'admin' | 'manager' | 'finance' | 'marketing' | 'staff';

interface Member {
    id: number;
    name: string | null;
    email: string;
    role: RoleValue;
    role_label: string;
    status: 'active' | 'pending' | 'expired';
    invited_at: string | null;
    is_you: boolean;
}

interface Role { value: Exclude<RoleValue, 'owner'>; label: string; description: string }

const statusBadge = {
    active: null,
    pending: <Badge tone="warning">Invite pending</Badge>,
    expired: <Badge tone="danger">Invite expired</Badge>,
};

export default function Team({
    members,
    roles,
    seats,
    canManage,
    isOwner,
    canInvite,
}: PageProps<{
    members: Member[];
    roles: Role[];
    seats: { used: number; limit: number | null };
    canManage: boolean;
    isOwner: boolean;
    canInvite: boolean;
}>) {
    const inviteForm = useForm<{ email: string; role: Role['value'] }>({ email: '', role: 'manager' });
    const grantableRoles = roles.filter((role) => isOwner || role.value !== 'admin');

    const invite = (e: FormEvent) => {
        e.preventDefault();
        inviteForm.post('/dashboard/team', { preserveScroll: true, onSuccess: () => inviteForm.reset('email') });
    };

    /** Mirrors the server rules: never the owner or yourself, and admins only by the owner. */
    const canModify = (member: Member) => canManage && member.role !== 'owner' && !member.is_you && (isOwner || member.role !== 'admin');

    const changeRole = (member: Member, role: string) =>
        router.patch(`/dashboard/team/${member.id}`, { role }, { preserveScroll: true });

    const resend = (member: Member) => router.post(`/dashboard/team/${member.id}/resend`, {}, { preserveScroll: true });

    const remove = (member: Member) => {
        const message = member.status === 'active'
            ? `Remove ${member.name ?? member.email} from the team? They lose access straight away.`
            : `Cancel the invitation to ${member.email}?`;
        if (confirm(message)) {
            router.delete(`/dashboard/team/${member.id}`, { preserveScroll: true });
        }
    };

    const seatsLabel = seats.limit === null ? `${seats.used} seats used · unlimited` : `${seats.used} of ${seats.limit} seats used`;

    return (
        <AppLayout title="Team" subtitle="Invite people to help run your events, and choose what each of them can do.">
            <Head title="Team" />

            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <Panel title="Members" subtitle={seatsLabel}>
                    <ul className="divide-y divide-tint/[0.07]">
                        {members.map((member) => (
                            <li key={member.id} className="flex flex-wrap items-center gap-3 py-3">
                                {member.name ? (
                                    <Avatar name={member.name} />
                                ) : (
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-tint/[0.06] text-slate-400">
                                        <Mail className="h-5 w-5" />
                                    </span>
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold text-ink">
                                        {member.name ?? member.email}
                                        {member.is_you && <span className="ml-1.5 text-xs font-medium text-slate-500">(you)</span>}
                                    </p>
                                    <p className="truncate text-xs text-slate-500">{member.name ? member.email : 'Has not accepted yet'}</p>
                                </div>
                                {statusBadge[member.status]}
                                {canModify(member) ? (
                                    <Select
                                        value={member.role}
                                        onChange={(e) => changeRole(member, e.target.value)}
                                        aria-label={`Role for ${member.name ?? member.email}`}
                                        className="h-9 w-auto py-0 text-sm"
                                    >
                                        {(member.role === 'admin' ? roles : grantableRoles).map((role) => (
                                            <option key={role.value} value={role.value}>{role.label}</option>
                                        ))}
                                    </Select>
                                ) : (
                                    <Badge tone={member.role === 'owner' ? 'brand' : 'neutral'}>{member.role_label}</Badge>
                                )}
                                {canModify(member) && member.status !== 'active' && (
                                    <button
                                        type="button"
                                        onClick={() => resend(member)}
                                        aria-label={`Resend invitation to ${member.email}`}
                                        className="grid h-9 w-9 shrink-0 place-items-center rounded-full text-slate-400 hover:bg-tint/[0.06] hover:text-ink"
                                    >
                                        <RotateCw className="h-4 w-4" />
                                    </button>
                                )}
                                {canModify(member) && (
                                    <button
                                        type="button"
                                        onClick={() => remove(member)}
                                        aria-label={member.status === 'active' ? `Remove ${member.name}` : `Cancel invitation to ${member.email}`}
                                        className="grid h-9 w-9 shrink-0 place-items-center rounded-full text-slate-400 hover:bg-rose-500/10 hover:text-rose-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                </Panel>

                <div className="space-y-5">
                    {canManage && (
                        <Panel title="Invite someone" subtitle="They get an email link to join. It works for 7 days.">
                            {canInvite ? (
                                <form onSubmit={invite} className="space-y-3">
                                    <Field label="Email" error={inviteForm.errors.email}>
                                        <Input
                                            type="email"
                                            value={inviteForm.data.email}
                                            onChange={(e) => inviteForm.setData('email', e.target.value)}
                                            placeholder="name@example.com"
                                            autoComplete="off"
                                        />
                                    </Field>
                                    <Field label="Role" error={inviteForm.errors.role}>
                                        <Select value={inviteForm.data.role} onChange={(e) => inviteForm.setData('role', e.target.value as Role['value'])}>
                                            {grantableRoles.map((role) => (
                                                <option key={role.value} value={role.value}>{role.label}</option>
                                            ))}
                                        </Select>
                                    </Field>
                                    <Button type="submit" className="w-full" disabled={inviteForm.processing || !inviteForm.data.email.trim()}>
                                        <UserPlus className="h-4 w-4" /> Send invitation
                                    </Button>
                                </form>
                            ) : (
                                <div className="space-y-3 text-sm text-slate-500">
                                    <p>Your plan has no free team seats. Upgrade to add more people, or remove someone first.</p>
                                    <Button href="/dashboard/subscription" variant="outline" size="sm">See plans</Button>
                                </div>
                            )}
                        </Panel>
                    )}

                    <Panel title={<span className="inline-flex items-center gap-2"><ShieldCheck className="h-5 w-5 text-brand-300" /> Who can do what</span>}>
                        <dl className="space-y-3 text-sm">
                            <div>
                                <dt className="font-semibold text-ink">Owner</dt>
                                <dd className="mt-0.5 text-slate-500">Full access, including billing and payouts.</dd>
                            </div>
                            {roles.map((role) => (
                                <div key={role.value}>
                                    <dt className="font-semibold text-ink">{role.label}</dt>
                                    <dd className="mt-0.5 text-slate-500">{role.description}</dd>
                                </div>
                            ))}
                        </dl>
                        <p className="mt-4 text-xs text-slate-500">Gate staff for a single event are added in each event’s check-in setup and don’t use a team seat.</p>
                    </Panel>
                </div>
            </div>
        </AppLayout>
    );
}
