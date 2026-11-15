import { DataTable } from '@/Components/app/DataTable';
import { Accent, EmptyState, Panel } from '@/Components/app/Kit';
import AdminLayout from '@/Layouts/AdminLayout';
import { cn, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { BarChart3, Download } from 'lucide-react';

type Revenue = { currency: string; minor: number };
type EventRow = { id: number; title: string; organization: string | null; currency: string; orders: number; revenue_minor: number };
type OrganizerRow = { id: number; name: string; currency: string; orders: number; revenue_minor: number };
type PromoterRow = { id: number; name: string; code: string | null; organization: string | null; currency: string; orders: number; revenue_minor: number };

type Props = {
    days: number;
    periods: number[];
    since: string;
    summary: { orders: number; revenue: Revenue[]; new_users: number; new_organizers: number; live_events: number };
    events: EventRow[];
    organizers: OrganizerRow[];
    promoters: PromoterRow[];
};

function Stat({ label, value, hint }: { label: string; value: string; hint?: string }) {
    return (
        <div className="rounded-[1.25rem] border border-tint/[0.07] bg-card p-5 shadow-[var(--shadow-card)]">
            <p className="text-xs font-bold uppercase tracking-[0.15em] text-slate-400">{label}</p>
            <p className="mt-2 text-2xl font-extrabold tabular-nums text-ink">{value}</p>
            {hint && <p className="mt-1 text-xs text-slate-400">{hint}</p>}
        </div>
    );
}

function ExportButton({ report, days }: { report: string; days: number }) {
    return (
        <a
            href={`/admin/reports/export?report=${report}&days=${days}`}
            className="inline-flex items-center gap-1.5 rounded-full border border-tint/10 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-tint/[0.05]"
        >
            <Download className="h-3.5 w-3.5" /> CSV
        </a>
    );
}

function revenueText(revenue: Revenue[]): string {
    if (revenue.length === 0) {
        return money(0);
    }

    return revenue.map((r) => money(r.minor, r.currency)).join(' · ');
}

export default function AdminReports({ days, periods, summary, events, organizers, promoters }: PageProps<Props>) {
    return (
        <AdminLayout
            title={<>Rep<Accent>orts</Accent></>}
            subtitle="Paid sales, organizers, promoters and sign-ups for the period you choose."
            actions={
                <div className="flex flex-wrap gap-1.5">
                    {periods.map((period) => (
                        <a
                            key={period}
                            href={`/admin/reports?days=${period}`}
                            className={cn(
                                'rounded-full px-3 py-1.5 text-xs font-semibold transition-colors',
                                period === days ? 'bg-brand-500 text-white' : 'border border-tint/10 text-slate-500 hover:text-ink',
                            )}
                        >
                            {period === 365 ? '1 year' : `${period} days`}
                        </a>
                    ))}
                </div>
            }
        >
            <Head title="Admin · Reports" />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Stat label="Paid orders" value={summary.orders.toLocaleString()} hint={`Last ${days} days`} />
                <Stat label="Revenue" value={revenueText(summary.revenue)} hint="Paid orders, by currency" />
                <Stat label="New users" value={summary.new_users.toLocaleString()} hint={`${summary.new_organizers} new organizers`} />
                <Stat label="Live events" value={summary.live_events.toLocaleString()} hint="Published right now" />
            </div>

            <div className="mt-8 grid gap-6 xl:grid-cols-2">
                <Panel title="Top events" action={<ExportButton report="events" days={days} />}>
                    <DataTable
                        rows={events}
                        rowKey={(r) => r.id}
                        empty={<EmptyState icon={BarChart3} title="No sales in this period" />}
                        columns={[
                            { header: 'Event', cell: (r) => <span className="font-semibold text-ink">{r.title}</span> },
                            { header: 'Orders', align: 'right', cell: (r) => <span className="tabular-nums">{r.orders}</span> },
                            { header: 'Revenue', align: 'right', cell: (r) => <span className="whitespace-nowrap tabular-nums">{money(r.revenue_minor, r.currency)}</span> },
                        ]}
                        mobile={(r) => (
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-bold text-ink">{r.title}</p>
                                <p className="text-xs text-slate-400">{r.orders} orders · {money(r.revenue_minor, r.currency)}</p>
                            </div>
                        )}
                    />
                </Panel>

                <Panel title="Top organizers" action={<ExportButton report="organizers" days={days} />}>
                    <DataTable
                        rows={organizers}
                        rowKey={(r) => `${r.id}-${r.currency}`}
                        empty={<EmptyState icon={BarChart3} title="No sales in this period" />}
                        columns={[
                            { header: 'Organizer', cell: (r) => <span className="font-semibold text-ink">{r.name}</span> },
                            { header: 'Orders', align: 'right', cell: (r) => <span className="tabular-nums">{r.orders}</span> },
                            { header: 'Revenue', align: 'right', cell: (r) => <span className="whitespace-nowrap tabular-nums">{money(r.revenue_minor, r.currency)}</span> },
                        ]}
                        mobile={(r) => (
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-bold text-ink">{r.name}</p>
                                <p className="text-xs text-slate-400">{r.orders} orders · {money(r.revenue_minor, r.currency)}</p>
                            </div>
                        )}
                    />
                </Panel>
            </div>

            <div className="mt-6">
                <Panel title="Promoter performance" action={<ExportButton report="promoters" days={days} />}>
                    <DataTable
                        rows={promoters}
                        rowKey={(r) => `${r.id}-${r.currency}`}
                        empty={<EmptyState icon={BarChart3} title="No promoter sales in this period" />}
                        columns={[
                            { header: 'Promoter', cell: (r) => <div><p className="font-semibold text-ink">{r.name}</p><p className="text-xs text-slate-400">{r.code} · {r.organization}</p></div> },
                            { header: 'Orders', align: 'right', cell: (r) => <span className="tabular-nums">{r.orders}</span> },
                            { header: 'Revenue', align: 'right', cell: (r) => <span className="whitespace-nowrap tabular-nums">{money(r.revenue_minor, r.currency)}</span> },
                        ]}
                        mobile={(r) => (
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-bold text-ink">{r.name}</p>
                                <p className="text-xs text-slate-400">{r.code} · {r.orders} orders · {money(r.revenue_minor, r.currency)}</p>
                            </div>
                        )}
                    />
                </Panel>
            </div>
        </AdminLayout>
    );
}
