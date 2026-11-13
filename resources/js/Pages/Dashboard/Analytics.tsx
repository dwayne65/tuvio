import { Accent, Panel } from '@/Components/app/Kit';
import { RankedBars, RevenueTrend } from '@/Components/Charts';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { StatCard } from '@/Components/StatCard';
import AppLayout from '@/Layouts/AppLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, usePoll } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Receipt, Ticket, TrendingUp, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';

type Props = {
    series: { date: string; revenue: number; tickets: number }[];
    totals: { gross_minor: number; net_minor: number; orders: number; tickets: number };
    top_events: { title: string; sold: number }[];
    today: { gross_minor: number; orders: number; checked_in: number };
    recent: { reference: string; buyer: string; event: string; total_minor: number; at: string | null }[];
    currency: string;
    generated_at: string;
};

/** "12s ago" style label that ticks every few seconds. */
function useAgo(iso: string | null) {
    const [now, setNow] = useState(Date.now());
    useEffect(() => {
        const id = setInterval(() => setNow(Date.now()), 5000);
        return () => clearInterval(id);
    }, []);
    if (!iso) return '';
    const s = Math.max(0, Math.round((now - new Date(iso).getTime()) / 1000));
    if (s < 60) return `${s}s ago`;
    if (s < 3600) return `${Math.floor(s / 60)}m ago`;
    if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
    return `${Math.floor(s / 86400)}d ago`;
}

function Ago({ iso }: { iso: string | null }) {
    return <>{useAgo(iso)}</>;
}

export default function Analytics({ series, totals, top_events, today, recent, currency, generated_at }: PageProps<Props>) {
    // Refresh the numbers every 15s while the tab is visible (Inertia pauses polling in background tabs).
    usePoll(15000, { only: ['series', 'totals', 'top_events', 'today', 'recent', 'generated_at'] });
    const updated = useAgo(generated_at);

    return (
        <AppLayout
            title={<>Analy<Accent>tics</Accent></>}
            subtitle={
                <span className="inline-flex items-center gap-2">
                    <span className="relative flex h-2 w-2">
                        <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                        <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                    </span>
                    Live · updated {updated}
                </span>
            }
        >
            <Head title="Analytics" />

            <Reveal>
                <div className="mb-5 grid grid-cols-3 divide-x divide-white/15 overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#0b1030] via-[#24115e] to-brand-700 p-5 text-white shadow-premium">
                    {[
                        { label: 'Sales today', value: money(today.gross_minor, currency) },
                        { label: 'Orders today', value: today.orders.toLocaleString() },
                        { label: 'Checked in today', value: today.checked_in.toLocaleString() },
                    ].map((s) => (
                        <div key={s.label} className="min-w-0 px-3 first:pl-0 last:pr-0">
                            <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-white/60">{s.label}</p>
                            <AnimatePresence mode="popLayout" initial={false}>
                                <motion.p
                                    key={s.value}
                                    initial={{ y: 12, opacity: 0 }}
                                    animate={{ y: 0, opacity: 1 }}
                                    exit={{ y: -12, opacity: 0 }}
                                    className="mt-1 truncate text-lg font-extrabold sm:text-2xl"
                                >
                                    {s.value}
                                </motion.p>
                            </AnimatePresence>
                        </div>
                    ))}
                </div>
            </Reveal>

            <RevealGroup className="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                <RevealItem><StatCard icon={TrendingUp} label="Gross sales" value={money(totals.gross_minor, currency)} /></RevealItem>
                <RevealItem><StatCard icon={Wallet} label="Net earnings" value={money(totals.net_minor, currency)} tone="emerald" /></RevealItem>
                <RevealItem><StatCard icon={Receipt} label="Paid orders" value={totals.orders.toLocaleString()} tone="blue" /></RevealItem>
                <RevealItem><StatCard icon={Ticket} label="Tickets sold" value={totals.tickets.toLocaleString()} tone="amber" /></RevealItem>
            </RevealGroup>

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <Reveal className="lg:col-span-2">
                    <Panel title="Revenue" subtitle="Gross ticket sales per day, last 30 days" bodyClassName="pt-4">
                        <RevenueTrend data={series} currency={currency} />
                    </Panel>
                </Reveal>
                <Reveal delay={0.1}>
                    <Panel title="Top events" subtitle="By tickets sold" className="h-full" bodyClassName="pt-4">
                        {top_events.length === 0 ? (
                            <p className="py-12 text-center text-sm text-slate-400">No sales yet.</p>
                        ) : (
                            <RankedBars data={top_events} />
                        )}
                    </Panel>
                </Reveal>
            </div>

            <Reveal className="mt-5">
                <Panel title="Latest sales" subtitle="New orders appear here automatically" bodyClassName="pt-2">
                    {recent.length === 0 ? (
                        <p className="py-10 text-center text-sm text-slate-400">No paid orders yet.</p>
                    ) : (
                        <ul className="divide-y divide-tint/[0.06]">
                            <AnimatePresence initial={false}>
                                {recent.map((o) => (
                                    <motion.li
                                        key={o.reference}
                                        layout
                                        initial={{ opacity: 0, backgroundColor: 'rgba(100,24,245,0.12)' }}
                                        animate={{ opacity: 1, backgroundColor: 'rgba(100,24,245,0)' }}
                                        transition={{ duration: 1.2 }}
                                        className="flex items-center justify-between gap-3 py-3 text-sm"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-ink">{o.buyer}</p>
                                            <p className="truncate text-xs text-slate-400">{o.event} · <Ago iso={o.at} /></p>
                                        </div>
                                        <span className="shrink-0 font-bold text-emerald-700">+{money(o.total_minor, currency)}</span>
                                    </motion.li>
                                ))}
                            </AnimatePresence>
                        </ul>
                    )}
                </Panel>
            </Reveal>
        </AppLayout>
    );
}
