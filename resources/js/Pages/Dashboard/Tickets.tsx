import { DataTable } from '@/Components/app/DataTable';
import { Accent, Avatar, EmptyState, FilterChips, Pagination, PillLink } from '@/Components/app/Kit';
import { Reveal } from '@/Components/motion/Reveal';
import { Badge } from '@/Components/ui/Badge';
import { Select } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Printer, Ticket as TicketIcon } from 'lucide-react';

type T = { code: string; status: string; attendee_name: string | null; attendee_email: string | null; event: string; type: string; issued_at: string | null; used_at: string | null };

type Props = {
    tickets: { data: T[]; links: { url: string | null; label: string; active: boolean }[] };
    events: { title: string; slug: string }[];
    filters: { event: string; status: string };
};

const tone: Record<string, 'success' | 'neutral' | 'danger'> = { issued: 'success', used: 'neutral', void: 'danger', refunded: 'danger' };
const statusLabel = (s: string) => (s === 'used' ? 'Checked in' : s === 'issued' ? 'Valid' : s);

const statuses = [
    { value: '', label: 'All' },
    { value: 'issued', label: 'Valid' },
    { value: 'used', label: 'Checked in' },
    { value: 'refunded', label: 'Refunded' },
];

export default function Tickets({ tickets, events, filters }: PageProps<Props>) {
    const apply = (patch: Record<string, string>) => router.get('/dashboard/tickets', { ...filters, ...patch }, { preserveState: true, preserveScroll: true });

    return (
        <AppLayout title={<>Tick<Accent>ets</Accent></>} subtitle="Every ticket issued for your events. Print any of them for an attendee.">
            <Head title="Tickets" />

            <Reveal className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <FilterChips options={statuses} value={filters.status ?? ''} onChange={(status) => apply({ status })} />
                <Select aria-label="Filter by event" value={filters.event ?? ''} onChange={(e) => apply({ event: e.target.value })} className="h-10 rounded-full sm:w-60">
                    <option value="">All events</option>
                    {events.map((e) => <option key={e.slug} value={e.slug}>{e.title}</option>)}
                </Select>
            </Reveal>

            <Reveal>
                <DataTable
                    rows={tickets.data}
                    rowKey={(t) => t.code}
                    empty={<EmptyState icon={TicketIcon} title="No tickets found" body="Try another filter, or share your event to start selling." />}
                    columns={[
                        {
                            header: 'Attendee',
                            cell: (t) => (
                                <div className="flex items-center gap-3">
                                    <Avatar name={t.attendee_name} className="h-9 w-9" />
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-ink">{t.attendee_name ?? '—'}</p>
                                        <p className="truncate text-xs text-slate-400">{t.attendee_email}</p>
                                    </div>
                                </div>
                            ),
                        },
                        { header: 'Event', cell: (t) => <span className="text-slate-600">{t.event}</span> },
                        { header: 'Type', cell: (t) => <span className="text-slate-600">{t.type}</span> },
                        { header: 'Code', cell: (t) => <span className="font-mono text-xs text-slate-500">{t.code}</span> },
                        { header: 'Status', cell: (t) => <Badge tone={tone[t.status] ?? 'neutral'}>{statusLabel(t.status)}</Badge> },
                        { header: 'Issued', cell: (t) => <span className="whitespace-nowrap text-slate-500">{t.issued_at ? formatDate(t.issued_at) : '—'}</span> },
                        {
                            header: 'Actions',
                            align: 'right',
                            cell: (t) => (
                                <PillLink href={`/dashboard/tickets/${t.code}/print`} newTab>
                                    <Printer className="h-4 w-4" /> Print
                                </PillLink>
                            ),
                        },
                    ]}
                    mobile={(t) => (
                        <div>
                            <div className="flex items-center gap-3">
                                <Avatar name={t.attendee_name} />
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-bold text-ink">{t.attendee_name ?? '—'}</p>
                                    <p className="truncate text-xs text-slate-400">{t.attendee_email}</p>
                                </div>
                                <Badge tone={tone[t.status] ?? 'neutral'}>{statusLabel(t.status)}</Badge>
                            </div>
                            <p className="mt-3 truncate text-sm text-slate-600">
                                {t.event} · <span className="text-slate-400">{t.type}</span>
                            </p>
                            <div className="mt-3 flex items-center justify-between gap-2 border-t border-tint/[0.06] pt-3">
                                <span className="truncate font-mono text-xs text-slate-500">{t.code}</span>
                                <PillLink href={`/dashboard/tickets/${t.code}/print`} newTab>
                                    <Printer className="h-4 w-4" /> Print
                                </PillLink>
                            </div>
                        </div>
                    )}
                />
            </Reveal>
            <Pagination links={tickets.links} />
        </AppLayout>
    );
}
