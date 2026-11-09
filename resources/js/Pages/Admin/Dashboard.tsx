import { Accent, Panel } from '@/Components/app/Kit';
import { RevenueTrend } from '@/Components/Charts';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { StatCard } from '@/Components/StatCard';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDateTime, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Building2, CalendarDays, Gift, HandCoins, Ticket, TrendingUp, Users, Wallet } from 'lucide-react';

interface Metrics {
    users: number; organizers: number; events: number; tickets_sold: number;
    gmv_minor: number; platform_revenue_minor: number; contributions_minor: number; pending_payouts_minor: number;
}

export default function AdminDashboard({ metrics, series, recent_orders }: PageProps<{
    metrics: Metrics;
    series: { date: string; revenue: number }[];
    recent_orders: { reference: string; event: string; total_minor: number; currency: string; at: string | null }[];
}>) {
    const tiles = [
        { label: 'Users', value: metrics.users.toLocaleString(), icon: Users, tone: 'brand' as const },
        { label: 'Organizers', value: metrics.organizers.toLocaleString(), icon: Building2, tone: 'blue' as const },
        { label: 'Published events', value: metrics.events.toLocaleString(), icon: CalendarDays, tone: 'brand' as const },
        { label: 'Tickets sold', value: metrics.tickets_sold.toLocaleString(), icon: Ticket, tone: 'emerald' as const },
        { label: 'GMV', value: money(metrics.gmv_minor), icon: TrendingUp, tone: 'brand' as const },
        { label: 'Platform revenue', value: money(metrics.platform_revenue_minor), icon: HandCoins, tone: 'emerald' as const },
        { label: 'Contributions', value: money(metrics.contributions_minor), icon: Gift, tone: 'amber' as const },
        { label: 'Pending payouts', value: money(metrics.pending_payouts_minor), icon: Wallet, tone: 'amber' as const },
    ];

    return (
        <AdminLayout title={<>Platform <Accent>overview</Accent></>} subtitle="Growth, payments and organizers at a glance.">
            <Head title="Admin" />
            <div className="relative mb-7 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#10162e] via-[#13244a] to-brand-700 p-6 text-white shadow-premium">
                <div className="dot-grid pointer-events-none absolute inset-0 opacity-40" />
                <div className="relative flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div><p className="text-[10px] font-bold uppercase tracking-[0.2em] text-white/60">Live platform pulse</p><h2 className="mt-2 text-2xl font-black tracking-[-0.04em]">Everything moving in one view.</h2><p className="mt-1 text-sm text-white/70">Monitor growth, payments, organizers and customer trust at a glance.</p></div>
                    <div className="rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-sm backdrop-blur"><span className="mr-2 inline-block h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_0_4px_rgb(52_211_153/0.15)]" />Systems operational</div>
                </div>
            </div>
            <RevealGroup className="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                {tiles.map((t) => (
                    <RevealItem key={t.label}><StatCard icon={t.icon} label={t.label} value={t.value} tone={t.tone} /></RevealItem>
                ))}
            </RevealGroup>

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
