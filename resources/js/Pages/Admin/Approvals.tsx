import { Accent, Avatar, EmptyState, Panel } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Textarea } from '@/Components/ui/Input';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDate, formatDateTime, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Building2, CalendarDays, Check, MapPin, ShieldCheck, X } from 'lucide-react';
import { useState } from 'react';

type PendingOrganization = {
    id: number;
    name: string;
    country: string | null;
    support_email: string | null;
    owner: { name: string; email: string; email_verified: boolean; joined_at: string } | null;
    submitted_at: string;
};

type PendingEvent = {
    id: number;
    slug: string;
    title: string;
    description: string;
    organization: string;
    category: string | null;
    type: string;
    visibility: string;
    start_at: string | null;
    place: string | null;
    cover_url: string | null;
    ticket_types: { name: string; price_minor: number; quantity_total: number | null }[];
    currency: string;
    submitted_at: string | null;
};

export default function AdminApprovals({ organizations, events }: PageProps<{ organizations: PendingOrganization[]; events: PendingEvent[] }>) {
    return (
        <AdminLayout title={<>Appro<Accent>vals</Accent></>} subtitle="New organizers and events wait here until you approve them. Nothing goes live without you.">
            <Head title="Admin · Approvals" />

            <div className="grid gap-5 xl:grid-cols-2">
                <Panel title={`Organizer accounts (${organizations.length})`} subtitle="Approve to open their dashboard." bodyClassName="pt-3">
                    {organizations.length === 0 ? (
                        <EmptyState icon={Building2} title="No organizers waiting" body="New organizer sign-ups appear here." />
                    ) : (
                        <ul className="space-y-3">
                            {organizations.map((org) => (
                                <li key={org.id} className="rounded-2xl border border-tint/[0.07] p-4">
                                    <div className="flex items-start gap-3">
                                        <Avatar name={org.name} className="h-10 w-10" />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-bold text-ink">{org.name}</p>
                                            {org.owner && (
                                                <p className="truncate text-sm text-slate-500">
                                                    {org.owner.name} · {org.owner.email}
                                                </p>
                                            )}
                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                {org.owner && (
                                                    <Badge tone={org.owner.email_verified ? 'success' : 'warning'}>{org.owner.email_verified ? 'Email verified' : 'Email not verified'}</Badge>
                                                )}
                                                {org.country && <Badge>{org.country}</Badge>}
                                                <Badge>Submitted {formatDateTime(org.submitted_at)}</Badge>
                                                {org.owner && <Badge>Joined {formatDate(org.owner.joined_at)}</Badge>}
                                            </div>
                                        </div>
                                    </div>
                                    <Decision approveUrl={`/admin/organizations/${org.id}/approve`} rejectUrl={`/admin/organizations/${org.id}/reject`} rejectHint="Tell the applicant why, e.g. details could not be verified." />
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>

                <Panel title={`Events to publish (${events.length})`} subtitle="Approve to put them live and on sale." bodyClassName="pt-3">
                    {events.length === 0 ? (
                        <EmptyState icon={CalendarDays} title="No events waiting" body="Events submitted for publishing appear here." />
                    ) : (
                        <ul className="space-y-3">
                            {events.map((event) => (
                                <li key={event.id} className="overflow-hidden rounded-2xl border border-tint/[0.07]">
                                    {event.cover_url && <img src={event.cover_url} alt="" className="h-36 w-full object-cover" />}
                                    <div className="p-4">
                                        <p className="font-bold text-ink">{event.title}</p>
                                        <p className="text-sm text-slate-500">by {event.organization}</p>
                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {event.category && <Badge tone="brand">{event.category}</Badge>}
                                            <Badge>{event.type}</Badge>
                                            <Badge>{event.visibility}</Badge>
                                            {event.start_at && <Badge><CalendarDays className="h-3 w-3" /> {formatDateTime(event.start_at)}</Badge>}
                                            {event.place && <Badge><MapPin className="h-3 w-3" /> {event.place}</Badge>}
                                        </div>
                                        {event.description && <p className="mt-3 line-clamp-4 text-sm text-slate-600">{event.description}</p>}
                                        <ul className="mt-3 divide-y divide-tint/[0.06] rounded-xl bg-tint/[0.03] px-3 text-sm">
                                            {event.ticket_types.map((t, i) => (
                                                <li key={i} className="flex justify-between py-2">
                                                    <span className="text-slate-600">{t.name}{t.quantity_total ? ` · ${t.quantity_total.toLocaleString()} available` : ''}</span>
                                                    <span className="font-semibold text-ink">{t.price_minor > 0 ? money(t.price_minor, event.currency) : 'Free'}</span>
                                                </li>
                                            ))}
                                        </ul>
                                        {event.submitted_at && <p className="mt-2 text-xs text-slate-400">Submitted {formatDateTime(event.submitted_at)}</p>}
                                        <Decision approveUrl={`/admin/events/${event.slug}/approve`} rejectUrl={`/admin/events/${event.slug}/reject`} rejectHint="What should the organizer change before it can go live?" />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </AdminLayout>
    );
}

function Decision({ approveUrl, rejectUrl, rejectHint }: { approveUrl: string; rejectUrl: string; rejectHint: string }) {
    const [rejecting, setRejecting] = useState(false);
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    function post(url: string, data: Record<string, string> = {}) {
        setBusy(true);
        router.post(url, data, {
            preserveScroll: true,
            onError: (errors) => setError(errors.reason ?? 'Something went wrong.'),
            onFinish: () => setBusy(false),
        });
    }

    if (rejecting) {
        return (
            <div className="mt-4 space-y-2">
                <Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={3} maxLength={500} placeholder={rejectHint} autoFocus />
                {error && <p className="text-sm text-rose-600">{error}</p>}
                <div className="flex gap-2">
                    <Button size="sm" variant="danger" disabled={busy || reason.trim().length === 0} onClick={() => post(rejectUrl, { reason })}>
                        <X className="h-4 w-4" /> Confirm rejection
                    </Button>
                    <Button size="sm" variant="ghost" onClick={() => setRejecting(false)}>Cancel</Button>
                </div>
            </div>
        );
    }

    return (
        <div className="mt-4 flex gap-2">
            <Button size="sm" disabled={busy} onClick={() => post(approveUrl)}>
                <Check className="h-4 w-4" /> Approve
            </Button>
            <Button size="sm" variant="outline" disabled={busy} onClick={() => setRejecting(true)}>
                <X className="h-4 w-4" /> Reject
            </Button>
            <span className="ml-auto hidden items-center gap-1 text-xs text-slate-400 sm:inline-flex"><ShieldCheck className="h-3.5 w-3.5" /> Logged to audit trail</span>
        </div>
    );
}
