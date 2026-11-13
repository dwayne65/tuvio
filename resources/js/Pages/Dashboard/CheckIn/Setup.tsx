import { EmptyState, Panel } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Field, Input, Select } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Activity, DoorOpen, ScanLine, ShieldCheck, Trash2, UserPlus, Users } from 'lucide-react';
import { FormEvent } from 'react';

interface Gate { id: number; name: string; is_active: boolean; checked_in: number }
interface StaffMember { id: number; role: 'scanner' | 'manager'; name: string; email: string; scans: number }

export default function CheckInSetup({
    event,
    gates,
    staff,
    organizationMembers,
}: PageProps<{ event: { id: number; title: string; slug: string; start_at: string | null }; gates: Gate[]; staff: StaffMember[]; organizationMembers: number }>) {
    const base = `/dashboard/check-in/${event.slug}`;
    const gateForm = useForm({ name: '' });
    const staffForm = useForm<{ email: string; role: 'scanner' | 'manager' }>({ email: '', role: 'scanner' });

    const addGate = (e: FormEvent) => {
        e.preventDefault();
        gateForm.post(`${base}/gates`, { preserveScroll: true, onSuccess: () => gateForm.reset() });
    };

    const addStaff = (e: FormEvent) => {
        e.preventDefault();
        staffForm.post(`${base}/staff`, { preserveScroll: true, onSuccess: () => staffForm.reset('email') });
    };

    const toggleGate = (gate: Gate) => router.patch(`${base}/gates/${gate.id}`, { is_active: !gate.is_active }, { preserveScroll: true });

    const removeGate = (gate: Gate) => {
        if (confirm(`Remove “${gate.name}”? Past check-ins keep the entrance name.`)) {
            router.delete(`${base}/gates/${gate.id}`, { preserveScroll: true });
        }
    };

    const removeStaff = (member: StaffMember) => {
        if (confirm(`Remove ${member.name}'s check-in access for this event?`)) {
            router.delete(`${base}/staff/${member.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout
            title="Check-in setup"
            subtitle={event.title}
            back={{ href: base, label: 'Scanner' }}
            actions={
                <div className="flex gap-2">
                    <Button href={`${base}/live`} variant="outline" size="sm"><Activity className="h-4 w-4" /> Live</Button>
                    <Button href={base} size="sm"><ScanLine className="h-4 w-4" /> Open scanner</Button>
                </div>
            }
        >
            <Head title={`Check-in setup · ${event.title}`} />

            <div className="grid gap-5 lg:grid-cols-2">
                <Panel title="Entrances" subtitle="Scanners pick their entrance, so you can see the flow at each door.">
                    <form onSubmit={addGate} className="flex items-start gap-2">
                        <div className="min-w-0 flex-1">
                            <Field error={gateForm.errors.name}>
                                <Input
                                    value={gateForm.data.name}
                                    onChange={(e) => gateForm.setData('name', e.target.value)}
                                    placeholder="e.g. Main gate, VIP entrance"
                                    maxLength={60}
                                    aria-label="Entrance name"
                                />
                            </Field>
                        </div>
                        <Button type="submit" disabled={gateForm.processing || !gateForm.data.name.trim()}>Add</Button>
                    </form>

                    {gates.length === 0 ? (
                        <p className="mt-4 rounded-2xl bg-tint/[0.04] px-4 py-3 text-sm text-slate-500">
                            No entrances yet. That’s fine for a single door — scans are recorded without an entrance.
                        </p>
                    ) : (
                        <ul className="mt-4 divide-y divide-tint/[0.07]">
                            {gates.map((gate) => (
                                <li key={gate.id} className="flex items-center gap-3 py-3">
                                    <span className={cn('grid h-10 w-10 shrink-0 place-items-center rounded-full', gate.is_active ? 'bg-brand-500/10 text-brand-300' : 'bg-tint/[0.06] text-slate-400')}>
                                        <DoorOpen className="h-5 w-5" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className={cn('truncate font-semibold', gate.is_active ? 'text-ink' : 'text-slate-400')}>{gate.name}</p>
                                        <p className="text-xs text-slate-500">{gate.checked_in.toLocaleString()} checked in</p>
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        aria-checked={gate.is_active}
                                        aria-label={`${gate.name} ${gate.is_active ? 'open' : 'closed'}`}
                                        onClick={() => toggleGate(gate)}
                                        className="shrink-0"
                                    >
                                        <Badge tone={gate.is_active ? 'success' : 'neutral'}>{gate.is_active ? 'Open' : 'Closed'}</Badge>
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => removeGate(gate)}
                                        aria-label={`Remove ${gate.name}`}
                                        className="grid h-9 w-9 shrink-0 place-items-center rounded-full text-slate-400 hover:bg-rose-500/10 hover:text-rose-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {gates.some((g) => !g.is_active) && (
                        <p className="mt-2 text-xs text-slate-500">Closed entrances are hidden from the scanner’s entrance list.</p>
                    )}
                </Panel>

                <Panel title="Gate staff" subtitle="Give volunteers or hired staff scanner access to this event only.">
                    <form onSubmit={addStaff} className="space-y-3">
                        <Field label="Email of their TUVIO account" error={staffForm.errors.email}>
                            <Input
                                type="email"
                                value={staffForm.data.email}
                                onChange={(e) => staffForm.setData('email', e.target.value)}
                                placeholder="name@example.com"
                                autoComplete="off"
                            />
                        </Field>
                        <div className="flex items-end gap-2">
                            <div className="min-w-0 flex-1">
                                <Field label="Access" error={staffForm.errors.role}>
                                    <Select value={staffForm.data.role} onChange={(e) => staffForm.setData('role', e.target.value as 'scanner' | 'manager')}>
                                        <option value="scanner">Scanner — scan tickets only</option>
                                        <option value="manager">Gate manager — scan + live dashboard</option>
                                    </Select>
                                </Field>
                            </div>
                            <Button type="submit" disabled={staffForm.processing || !staffForm.data.email.trim()}>
                                <UserPlus className="h-4 w-4" /> Add
                            </Button>
                        </div>
                    </form>

                    {staff.length === 0 ? (
                        <div className="mt-4">
                            <EmptyState icon={Users} title="No gate staff yet" body="Your team members can already scan every event." />
                        </div>
                    ) : (
                        <ul className="mt-4 divide-y divide-tint/[0.07]">
                            {staff.map((member) => (
                                <li key={member.id} className="flex items-center gap-3 py-3">
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-tint/[0.06] text-sm font-bold text-ink">
                                        {member.name.slice(0, 1).toUpperCase()}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-semibold text-ink">{member.name}</p>
                                        <p className="truncate text-xs text-slate-500">{member.email} · {member.scans.toLocaleString()} scans</p>
                                    </div>
                                    <Badge tone={member.role === 'manager' ? 'brand' : 'neutral'}>{member.role === 'manager' ? 'Gate manager' : 'Scanner'}</Badge>
                                    <button
                                        type="button"
                                        onClick={() => removeStaff(member)}
                                        aria-label={`Remove ${member.name}`}
                                        className="grid h-9 w-9 shrink-0 place-items-center rounded-full text-slate-400 hover:bg-rose-500/10 hover:text-rose-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>

            <Panel className="mt-5" title={<span className="inline-flex items-center gap-2"><ShieldCheck className="h-5 w-5 text-brand-300" /> Who can do what</span>}>
                <dl className="grid gap-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt className="font-semibold text-ink">Your team ({organizationMembers})</dt>
                        <dd className="mt-1 text-slate-500">Scan every event and view live dashboards. Owners, admins and managers can also change this setup.</dd>
                    </div>
                    <div>
                        <dt className="font-semibold text-ink">Gate managers</dt>
                        <dd className="mt-1 text-slate-500">Scan and view this event’s live dashboard. No access to sales, payouts or settings.</dd>
                    </div>
                    <div>
                        <dt className="font-semibold text-ink">Scanners</dt>
                        <dd className="mt-1 text-slate-500">Scan tickets for this event only, online or offline. Every scan records who made it.</dd>
                    </div>
                </dl>
            </Panel>
        </AppLayout>
    );
}
