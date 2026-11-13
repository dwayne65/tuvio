import { DataTable } from '@/Components/app/DataTable';
import { Accent, EmptyState, Panel, PillLink } from '@/Components/app/Kit';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { StatCard } from '@/Components/StatCard';
import { Badge } from '@/Components/ui/Badge';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronRight, Download, FileBarChart, Ticket, TrendingUp, UserCheck, Wallet } from 'lucide-react';

type Row = {
    title: string; slug: string; status: string; start_at: string | null; currency: string;
    orders: number; sold: number; checked_in: number; attendance: number | null;
    gross_minor: number; discount_minor: number; commission_minor: number; net_minor: number;
};
type TypeRow = { name: string; capacity: number | null; sold: number; checked_in: number; attendance: number | null; revenue_minor: number };

function Attendance({ value, sold, checked }: { value: number | null; sold: number; checked: number }) {
    return (
        <div className="min-w-[8rem]">
            <div className="flex items-baseline justify-between gap-2 text-xs">
                <span className="font-semibold text-ink">{checked} / {sold}</span>
                <span className="text-slate-400">{value === null ? '—' : `${value}%`}</span>
            </div>
            <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-tint/[0.08]">
                <div className="h-full rounded-full bg-gradient-to-r from-brand-500 to-emerald-500" style={{ width: `${value ?? 0}%` }} />
            </div>
        </div>
    );
}

export default function Reports({ rows, totals, selected, currency }: PageProps<{
    rows: Row[];
    totals: { sold: number; checked_in: number; orders: number; gross_minor: number; discount_minor: number; net_minor: number };
    selected: { title: string; slug: string; types: TypeRow[] } | null;
    currency: string;
}>) {
    const attendance = totals.sold > 0 ? Math.round((totals.checked_in / totals.sold) * 1000) / 10 : null;

    return (
        <AppLayout
            title={<>Re<Accent>ports</Accent></>}
            subtitle="Sales and attendance for every event."
            actions={<PillLink href="/dashboard/reports/export"><Download className="h-4 w-4" /> Export CSV</PillLink>}
        >
            <Head title="Reports" />

            <RevealGroup className="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                <RevealItem><StatCard icon={TrendingUp} label="Gross sales" value={money(totals.gross_minor, currency)} /></RevealItem>
                <RevealItem><StatCard icon={Wallet} label="Net to you" value={money(totals.net_minor, currency)} tone="emerald" /></RevealItem>
                <RevealItem><StatCard icon={Ticket} label="Tickets sold" value={totals.sold.toLocaleString()} tone="blue" /></RevealItem>
                <RevealItem><StatCard icon={UserCheck} label="Attendance" value={attendance === null ? '—' : `${attendance}%`} tone="amber" /></RevealItem>
            </RevealGroup>

            {selected && (
                <Reveal className="mt-5">
                    <Panel
                        title={selected.title}
                        subtitle="Breakdown by ticket type"
                        action={<Link href="/dashboard/reports" preserveScroll className="text-sm font-semibold text-brand-300">Close</Link>}
                    >
                        <DataTable
                            rows={selected.types}
                            rowKey={(t) => t.name}
                            empty={<p className="py-8 text-center text-sm text-slate-400">No ticket types.</p>}
                            columns={[
                                { header: 'Ticket type', cell: (t) => <span className="font-semibold text-ink">{t.name}</span> },
                                { header: 'Sold', align: 'right', cell: (t) => `${t.sold}${t.capacity ? ` / ${t.capacity}` : ''}` },
                                { header: 'Attendance', cell: (t) => <Attendance value={t.attendance} sold={t.sold} checked={t.checked_in} /> },
                                { header: 'Revenue', align: 'right', cell: (t) => <span className="font-bold text-ink">{money(t.revenue_minor, currency)}</span> },
                            ]}
                            mobile={(t) => (
                                <div>
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="font-bold text-ink">{t.name}</p>
                                        <p className="font-bold text-ink">{money(t.revenue_minor, currency)}</p>
                                    </div>
                                    <p className="mb-2 text-xs text-slate-400">{t.sold}{t.capacity ? ` of ${t.capacity}` : ''} sold</p>
                                    <Attendance value={t.attendance} sold={t.sold} checked={t.checked_in} />
                                </div>
                            )}
                        />
                    </Panel>
                </Reveal>
            )}

            <div className="mt-5">
                <DataTable
                    rows={rows}
                    rowKey={(r) => r.slug}
                    empty={<EmptyState icon={FileBarChart} title="No events yet" body="Reports appear once you create an event and start selling." />}
                    columns={[
                        {
                            header: 'Event',
                            cell: (r) => (
                                <div className="min-w-0">
                                    <p className="truncate font-semibold text-ink">{r.title}</p>
                                    <p className="text-xs text-slate-400">{r.start_at ? formatDate(r.start_at) : 'No date'} · <span className="capitalize">{r.status}</span></p>
                                </div>
                            ),
                        },
                        { header: 'Orders', align: 'right', cell: (r) => r.orders },
                        { header: 'Attendance', cell: (r) => <Attendance value={r.attendance} sold={r.sold} checked={r.checked_in} /> },
                        { header: 'Gross', align: 'right', cell: (r) => <span className="whitespace-nowrap">{money(r.gross_minor, r.currency)}</span> },
                        { header: 'Discounts', align: 'right', cell: (r) => <span className="whitespace-nowrap text-slate-500">{r.discount_minor > 0 ? `−${money(r.discount_minor, r.currency)}` : '—'}</span> },
                        { header: 'Net', align: 'right', cell: (r) => <span className="whitespace-nowrap font-bold text-ink">{money(r.net_minor, r.currency)}</span> },
                        {
                            header: '',
                            align: 'right',
                            cell: (r) => (
                                <Link href={`/dashboard/reports?event=${r.slug}`} preserveScroll className={cn('inline-flex items-center text-sm font-semibold', selected?.slug === r.slug ? 'text-ink' : 'text-brand-300')}>
                                    Details <ChevronRight className="h-4 w-4" />
                                </Link>
                            ),
                        },
                    ]}
                    mobile={(r) => (
                        <Link href={`/dashboard/reports?event=${r.slug}`} preserveScroll className="block">
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="truncate font-bold text-ink">{r.title}</p>
                                    <p className="text-xs text-slate-400">{r.start_at ? formatDate(r.start_at) : 'No date'} · {r.orders} orders</p>
                                </div>
                                {r.status === 'published' ? <Badge tone="success">Live</Badge> : <Badge tone="neutral">{r.status}</Badge>}
                            </div>
                            <div className="mt-3 grid grid-cols-2 gap-3 border-t border-tint/[0.06] pt-3">
                                <Attendance value={r.attendance} sold={r.sold} checked={r.checked_in} />
                                <div className="text-right">
                                    <p className="font-bold text-ink">{money(r.net_minor, r.currency)}</p>
                                    <p className="text-[11px] text-slate-400">net of {money(r.gross_minor, r.currency)}</p>
                                </div>
                            </div>
                        </Link>
                    )}
                />
            </div>
        </AppLayout>
    );
}
