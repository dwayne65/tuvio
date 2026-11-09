import { DataTable } from '@/Components/app/DataTable';
import { Accent, Avatar, EmptyState, Pagination, PillLink } from '@/Components/app/Kit';
import { Reveal } from '@/Components/motion/Reveal';
import { Badge } from '@/Components/ui/Badge';
import { Select } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Download, Printer, Users } from 'lucide-react';

type A = { name: string | null; email: string | null; event: string; type: string; checked_in: boolean; code: string };

type Props = {
    attendees: { data: A[]; links: { url: string | null; label: string; active: boolean }[] };
    summary: { total: number; checked_in: number };
    events: { title: string; slug: string }[];
    filters: { event: string };
};

export default function Attendees({ attendees, summary, events, filters }: PageProps<Props>) {
    const qs = filters.event ? `?event=${filters.event}` : '';
    const pct = summary.total > 0 ? Math.round((summary.checked_in / summary.total) * 100) : 0;

    return (
        <AppLayout
            title={<>Atten<Accent>dees</Accent></>}
            subtitle="Who’s coming, and who’s already through the gate."
            actions={
                <PillLink href={`/dashboard/attendees/export${qs}`}>
                    <Download className="h-4 w-4" /> Export CSV
                </PillLink>
            }
        >
            <Head title="Attendees" />

            {/* Arrival progress */}
            <Reveal className="mb-5">
                <div className="rounded-[1.5rem] border border-tint/[0.07] bg-card p-5 shadow-[var(--shadow-card)] sm:p-6">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-slate-400">Arrivals</p>
                            <p className="mt-1 text-3xl font-extrabold tracking-tight text-ink">
                                {summary.checked_in.toLocaleString()}
                                <span className="text-lg font-semibold text-slate-400"> / {summary.total.toLocaleString()}</span>
                            </p>
                        </div>
                        <div className="flex gap-6 text-sm">
                            <Stat label="Checked in" value={summary.checked_in} dot="bg-emerald-500" />
                            <Stat label="Yet to arrive" value={summary.total - summary.checked_in} dot="bg-slate-300" />
                        </div>
                    </div>
                    <div className="mt-4 h-2.5 overflow-hidden rounded-full bg-tint/[0.06]" role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100} aria-label="Checked in">
                        <div className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500 transition-all" style={{ width: `${pct}%` }} />
                    </div>
                    <p className="mt-2 text-xs text-slate-400">{pct}% checked in</p>
                </div>
            </Reveal>

            <Reveal className="mb-4">
                <Select
                    aria-label="Filter by event"
                    value={filters.event ?? ''}
                    onChange={(e) => router.get('/dashboard/attendees', { event: e.target.value }, { preserveState: true, preserveScroll: true })}
                    className="h-10 rounded-full sm:w-64"
                >
                    <option value="">All events</option>
                    {events.map((e) => <option key={e.slug} value={e.slug}>{e.title}</option>)}
                </Select>
            </Reveal>

            <Reveal>
                <DataTable
                    rows={attendees.data}
                    rowKey={(a) => a.code}
                    empty={<EmptyState icon={Users} title="No attendees yet" body="Attendees appear here as soon as tickets are sold." />}
                    columns={[
                        {
                            header: 'Attendee',
                            cell: (a) => (
                                <div className="flex items-center gap-3">
                                    <Avatar name={a.name} className="h-9 w-9" />
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-ink">{a.name ?? '—'}</p>
                                        <p className="truncate text-xs text-slate-400">{a.email}</p>
                                    </div>
                                </div>
                            ),
                        },
                        { header: 'Event', cell: (a) => <span className="text-slate-600">{a.event}</span> },
                        { header: 'Type', cell: (a) => <span className="text-slate-600">{a.type}</span> },
                        { header: 'Entry', cell: (a) => (a.checked_in ? <Badge tone="success">Checked in</Badge> : <Badge tone="neutral">Not yet</Badge>) },
                        {
                            header: 'Actions',
                            align: 'right',
                            cell: (a) => (
                                <PillLink href={`/dashboard/tickets/${a.code}/print`} newTab>
                                    <Printer className="h-4 w-4" /> Print
                                </PillLink>
                            ),
                        },
                    ]}
                    mobile={(a) => (
                        <div className="flex items-center gap-3">
                            <Avatar name={a.name} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-bold text-ink">{a.name ?? '—'}</p>
                                <p className="truncate text-xs text-slate-400">{a.event} · {a.type}</p>
                                <div className="mt-1.5">{a.checked_in ? <Badge tone="success">Checked in</Badge> : <Badge tone="neutral">Not yet</Badge>}</div>
                            </div>
                            <a
                                href={`/dashboard/tickets/${a.code}/print`}
                                target="_blank"
                                rel="noopener"
                                aria-label={`Print ticket for ${a.name ?? 'attendee'}`}
                                className="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-tint/[0.1] text-slate-500 hover:border-brand-400 hover:text-brand-600"
                            >
                                <Printer className="h-4 w-4" />
                            </a>
                        </div>
                    )}
                />
            </Reveal>
            <Pagination links={attendees.links} />
        </AppLayout>
    );
}

function Stat({ label, value, dot }: { label: string; value: number; dot: string }) {
    return (
        <div>
            <p className="flex items-center gap-1.5 text-xs text-slate-400">
                <span className={`h-2 w-2 rounded-full ${dot}`} /> {label}
            </p>
            <p className="mt-0.5 font-bold text-ink">{value.toLocaleString()}</p>
        </div>
    );
}
