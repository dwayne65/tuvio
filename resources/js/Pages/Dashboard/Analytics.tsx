import { Accent, Panel } from '@/Components/app/Kit';
import { RankedBars, RevenueTrend } from '@/Components/Charts';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { StatCard } from '@/Components/StatCard';
import AppLayout from '@/Layouts/AppLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Receipt, Ticket, TrendingUp, Wallet } from 'lucide-react';

type Props = {
    series: { date: string; revenue: number; tickets: number }[];
    totals: { gross_minor: number; net_minor: number; orders: number; tickets: number };
    top_events: { title: string; sold: number }[];
    currency: string;
};

export default function Analytics({ series, totals, top_events, currency }: PageProps<Props>) {
    return (
        <AppLayout title={<>Analy<Accent>tics</Accent></>} subtitle="Sales performance over the last 30 days.">
            <Head title="Analytics" />
            <RevealGroup className="grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                <RevealItem><StatCard icon={TrendingUp} label="Gross sales" value={money(totals.gross_minor, currency)} /></RevealItem>
                <RevealItem><StatCard icon={Wallet} label="Net earnings" value={money(totals.net_minor, currency)} tone="emerald" /></RevealItem>
                <RevealItem><StatCard icon={Receipt} label="Paid orders" value={totals.orders.toLocaleString()} tone="blue" /></RevealItem>
                <RevealItem><StatCard icon={Ticket} label="Tickets sold" value={totals.tickets.toLocaleString()} tone="amber" /></RevealItem>
            </RevealGroup>

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <Reveal className="lg:col-span-2">
                    <Panel title="Revenue" subtitle="Gross ticket sales per day" bodyClassName="pt-4">
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
        </AppLayout>
    );
}
