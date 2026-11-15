import { Accent, Panel } from '@/Components/app/Kit';
import { RevenueTrend, TrafficTrend } from '@/Components/Charts';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { StatCard } from '@/Components/StatCard';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDateTime, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { ArrowDownToLine, Building2, CalendarDays, CreditCard, Eye, HandCoins, Monitor, Smartphone, Tablet, Ticket, TrendingUp, UserRound, Users, Wallet } from 'lucide-react';
import { Button } from '@/Components/ui/Button';

interface Metrics {
    users: number; organizers: number; events: number; tickets_sold: number;
    gmv_minor: number; platform_revenue_minor: number; contributions_minor: number; pending_payouts_minor: number;
    commission_minor: number; subscriptions_minor: number; available_minor: number;
}

interface Traffic {
    today: { views: number; visitors: number };
    week: { views: number; visitors: number };
    month: { views: number; visitors: number; views_change: number | null };
    all_time_views: number;
    series: { date: string; views: number; visitors: number }[];
    top_pages: { path: string; label: string; views: number }[];
    sources: { source: string; visits: number }[];
    devices: Partial<Record<'mobile' | 'desktop' | 'tablet', number>>;
}

export default function AdminDashboard({ metrics, series, traffic, recent_orders }: PageProps<{
    metrics: Metrics;
    traffic: Traffic;
    series: { date: string; revenue: number }[];
    recent_orders: { reference: string; event: string; total_minor: number; currency: string; at: string | null }[];
}>) {
    const tiles = [
        { label: 'Users', value: metrics.users.toLocaleString(), icon: Users, tone: 'brand' as const },
        { label: 'Organizers', value: metrics.organizers.toLocaleString(), icon: Building2, tone: 'blue' as const },
        { label: 'Published events', value: metrics.events.toLocaleString(), icon: CalendarDays, tone: 'brand' as const },
        { label: 'Tickets sold', value: metrics.tickets_sold.toLocaleString(), icon: Ticket, tone: 'emerald' as const },
        { label: 'GMV', value: money(metrics.gmv_minor), icon: TrendingUp, tone: 'brand' as const },
        { label: 'Commission & fees', value: money(metrics.commission_minor), icon: HandCoins, tone: 'emerald' as const },
        { label: 'Plan payments', value: money(metrics.subscriptions_minor), icon: CreditCard, tone: 'blue' as const },
        { label: 'Pending payouts', value: money(metrics.pending_payouts_minor), icon: Wallet, tone: 'amber' as const },
    ];

    return (
        <AdminLayout title={<>Platform <Accent>overview</Accent></>} subtitle="Growth, payments and organizers at a glance.">
            <Head title="Admin" />
            <div className="relative mb-7 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#0b1030] via-[#24115e] to-brand-700 p-6 text-white shadow-premium">
                <div className="dot-grid pointer-events-none absolute inset-0 opacity-40" />
                <div className="relative flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div><p className="text-[10px] font-bold uppercase tracking-[0.2em] text-white/60">Live platform pulse</p><h2 className="mt-2 text-2xl font-black tracking-[-0.04em]">Everything moving in one view.</h2><p className="mt-1 text-sm text-white/70">Monitor growth, payments, organizers and customer trust at a glance.</p></div>
                    <div className="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur">
                        <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-white/60">Your earnings to withdraw</p>
                        <p className="mt-1 text-2xl font-extrabold">{money(metrics.available_minor)}</p>
                        <Button href="/admin/earnings" size="sm" variant="secondary" className="mt-3 bg-white text-[#0b0d12]"><ArrowDownToLine className="h-4 w-4" /> View & withdraw</Button>
                    </div>
                </div>
            </div>
            <RevealGroup className="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                {tiles.map((t) => (
                    <RevealItem key={t.label}><StatCard icon={t.icon} label={t.label} value={t.value} tone={t.tone} /></RevealItem>
                ))}
            </RevealGroup>

            <PlatformTraffic traffic={traffic} />

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <Reveal className="lg:col-span-2">
                    <Panel title="Platform GMV" subtitle="Gross value of paid orders, last 30 days" bodyClassName="pt-4">
                        <RevenueTrend data={series} />
                    </Panel>
                </Reveal>

                <Reveal delay={0.1}>
                    <Panel title="Recent paid orders" className="h-full" bodyClassName="pt-2">
                            <div className="divide-y divide-tint/[0.06]">
                                {recent_orders.length === 0 && <p className="py-8 text-center text-slate-400">No paid orders yet.</p>}
                                {recent_orders.map((o) => (
                                    <div key={o.reference} className="flex items-center justify-between gap-3 py-3 text-sm">
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-ink">{o.event}</p>
                                            <p className="font-mono text-xs text-slate-400">{o.reference}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-semibold text-ink">{money(o.total_minor, o.currency)}</p>
                                            {o.at && <p className="text-xs text-slate-400">{formatDateTime(o.at)}</p>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                    </Panel>
                </Reveal>
            </div>
        </AdminLayout>
    );
}

function change(value: number | null): string | undefined {
    if (value === null) {
        return undefined;
    }

    return `${value > 0 ? '+' : ''}${value}% vs previous 30 days`;
}

/** Who is visiting the platform: views, unique visitors, top pages, sources and devices. */
function PlatformTraffic({ traffic }: { traffic: Traffic }) {
    const tiles = [
        { label: 'Views today', value: traffic.today.views.toLocaleString(), hint: `${traffic.today.visitors.toLocaleString()} visitors`, icon: Eye, tone: 'brand' as const },
        { label: 'Views, 7 days', value: traffic.week.views.toLocaleString(), hint: `${traffic.week.visitors.toLocaleString()} visitors`, icon: Eye, tone: 'blue' as const },
        { label: 'Views, 30 days', value: traffic.month.views.toLocaleString(), hint: change(traffic.month.views_change), icon: TrendingUp, tone: 'emerald' as const },
        { label: 'Visitors, 30 days', value: traffic.month.visitors.toLocaleString(), hint: `${traffic.all_time_views.toLocaleString()} views all time`, icon: UserRound, tone: 'amber' as const },
    ];
    const topViews = Math.max(1, ...traffic.top_pages.map((p) => p.views));
    const totalVisits = Math.max(1, traffic.sources.reduce((sum, s) => sum + s.visits, 0));
    const deviceTotal = Math.max(1, Object.values(traffic.devices).reduce((sum, n) => sum + (n ?? 0), 0));
    const devices = [
        { key: 'mobile' as const, label: 'Mobile', icon: Smartphone },
        { key: 'desktop' as const, label: 'Desktop', icon: Monitor },
        { key: 'tablet' as const, label: 'Tablet', icon: Tablet },
    ];

    return (
        <section className="mt-7">
            <div className="mb-3 flex items-end justify-between gap-3">
                <div>
                    <h2 className="text-lg font-extrabold tracking-[-0.03em] text-ink">Platform traffic</h2>
                    <p className="text-sm text-slate-500">Pages people open on TUVIO. Bots and admins are not counted.</p>
                </div>
            </div>
            <RevealGroup className="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                {tiles.map((t) => (
                    <RevealItem key={t.label}><StatCard icon={t.icon} label={t.label} value={t.value} hint={t.hint} tone={t.tone} /></RevealItem>
                ))}
            </RevealGroup>

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <Reveal className="lg:col-span-2">
                    <Panel
                        title="Views & visitors"
                        subtitle="Last 30 days"
                        action={
                            <div className="flex items-center gap-3 text-xs font-semibold text-slate-500">
                                <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-[#6418f5]" /> Page views</span>
                                <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 rounded-full bg-[#1fb8a6]" /> Visitors</span>
                            </div>
                        }
                        bodyClassName="pt-4"
                    >
                        <TrafficTrend data={traffic.series} />
                    </Panel>
                </Reveal>

                <Reveal delay={0.1}>
                    <Panel title="Where visitors come from" subtitle="Last 30 days" className="h-full" bodyClassName="pt-2">
                        {traffic.sources.length === 0 ? (
                            <p className="py-8 text-center text-slate-400">No visits recorded yet.</p>
                        ) : (
                            <ul className="space-y-3 py-2">
                                {traffic.sources.map((s) => (
                                    <li key={s.source}>
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <span className="truncate font-semibold text-ink">{s.source}</span>
                                            <span className="shrink-0 tabular-nums text-slate-500">{s.visits.toLocaleString()} · {Math.round((s.visits / totalVisits) * 100)}%</span>
                                        </div>
                                        <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-tint/[0.06]">
                                            <div className="h-full rounded-full bg-brand-500" style={{ width: `${(s.visits / totalVisits) * 100}%` }} />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <div className="mt-4 grid grid-cols-3 gap-2 border-t border-tint/[0.06] pt-4">
                            {devices.map(({ key, label, icon: Icon }) => (
                                <div key={key} className="rounded-xl bg-tint/[0.04] px-2 py-2.5 text-center">
                                    <Icon className="mx-auto h-4 w-4 text-slate-400" />
                                    <p className="mt-1 text-sm font-bold text-ink">{Math.round(((traffic.devices[key] ?? 0) / deviceTotal) * 100)}%</p>
                                    <p className="text-[11px] text-slate-500">{label}</p>
                                </div>
                            ))}
                        </div>
                    </Panel>
                </Reveal>
            </div>

            <Reveal className="mt-5">
                <Panel title="Most viewed pages" subtitle="Last 30 days" bodyClassName="pt-2">
                    {traffic.top_pages.length === 0 ? (
                        <p className="py-8 text-center text-slate-400">No page views recorded yet.</p>
                    ) : (
                        <ul className="grid gap-x-8 gap-y-3 py-2 md:grid-cols-2">
                            {traffic.top_pages.map((p) => (
                                <li key={p.path}>
                                    <div className="flex items-center justify-between gap-3 text-sm">
                                        <a href={p.path} target="_blank" rel="noopener noreferrer" className="min-w-0 truncate font-semibold text-ink hover:text-brand-500">
                                            {p.label} <span className="font-normal text-slate-400">{p.path}</span>
                                        </a>
                                        <span className="shrink-0 tabular-nums text-slate-500">{p.views.toLocaleString()}</span>
                                    </div>
                                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-tint/[0.06]">
                                        <div className="h-full rounded-full bg-[#1fb8a6]" style={{ width: `${(p.views / topViews) * 100}%` }} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </Reveal>
        </section>
    );
}
