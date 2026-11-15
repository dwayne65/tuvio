import { Accent, EmptyState, Panel } from '@/Components/app/Kit';
import { StatCard } from '@/Components/StatCard';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { cn, formatDateTime, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Check, Copy, Hash, PhoneCall, Radio, ShieldAlert, ShieldCheck, Ticket, Users } from 'lucide-react';
import { useState } from 'react';

type Setup = { shortcode: string | null; webhook_url: string; signature_configured: boolean; events_live: number };
type Stats = {
    sessions: number; callers: number; direct_dials: number; orders: number; paid_orders: number;
    ticket_sales_minor: number; contributions_minor: number; tickets_sent: number;
};
type Step = { label: string; count: number };
type Network = { network: string; count: number };
type Day = { date: string; sessions: number; paid: number };
type TopEvent = { event_id: number; title: string; dial: string | null; sessions: number; paid: number };
type Session = {
    id: number; caller: string; network: string | null; event: string | null; direct: boolean; screens: number;
    outcome: string | null; last_step: string | null; started_at: string; duration_seconds: number;
};

const outcomes: Record<string, { label: string; tone: 'success' | 'warning' | 'neutral' | 'danger' | 'brand' }> = {
    order: { label: 'Order placed', tone: 'success' },
    contribution: { label: 'Contributed', tone: 'success' },
    tickets_viewed: { label: 'Checked tickets', tone: 'brand' },
    tickets_sent: { label: 'Tickets by SMS', tone: 'brand' },
    cancelled: { label: 'Cancelled', tone: 'warning' },
    failed: { label: 'Failed', tone: 'danger' },
};

const steps: Record<string, string> = {
    main: 'main menu', event: 'event list', event_code: 'event code', ticket_type: 'ticket choice', quantity: 'quantity',
    buyer_name: 'name', confirm_order: 'confirming', campaign: 'campaigns', amount: 'amount',
    contributor_name: 'name', confirm_contribution: 'confirming', my_tickets: 'my tickets',
};

const percent = (part: number, whole: number) => (whole > 0 ? Math.round((part / whole) * 100) : 0);

export default function AdminUssd({ days, periods, currency, setup, stats, funnel, networks, daily, topEvents, recent }: PageProps<{
    days: number; periods: number[]; currency: string; setup: Setup; stats: Stats; funnel: Step[];
    networks: Network[]; daily: Day[]; topEvents: TopEvent[]; recent: Session[];
}>) {
    const peak = Math.max(1, ...daily.map((d) => d.sessions));
    const dial = setup.shortcode ? `*${setup.shortcode}#` : 'the TUVIO code';

    return (
        <AdminLayout
            title={<>USSD <Accent>channel</Accent></>}
            subtitle={`Buyers without data dial ${dial} to buy tickets and contribute with Mobile Money.`}
            actions={
                <div className="flex rounded-full bg-tint/[0.05] p-1 text-sm font-semibold">
                    {periods.map((p) => (
                        <Link
                            key={p}
                            href={`/admin/ussd?days=${p}`}
                            preserveScroll
                            className={cn('rounded-full px-3.5 py-1.5 transition-colors', p === days ? 'bg-card text-ink shadow-sm' : 'text-slate-500 hover:text-ink')}
                        >
                            {p === 1 ? 'Today' : `${p} days`}
                        </Link>
                    ))}
                </div>
            }
        >
            <Head title="Admin · USSD" />

            <Reveal>
                <div className="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#0b1030] via-[#24115e] to-brand-700 p-6 text-white shadow-premium sm:p-7">
                    <div className="dot-grid pointer-events-none absolute inset-0 opacity-30" />
                    <div className="relative grid gap-6 lg:grid-cols-[1fr_1.3fr]">
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-white/60">Shortcode</p>
                            {setup.shortcode ? (
                                <>
                                    <p className="mt-2 font-mono text-[2.6rem] font-extrabold leading-none tracking-[-0.03em] sm:text-5xl">*{setup.shortcode}#</p>
                                    <p className="mt-3 text-sm text-white/70">
                                        {setup.events_live} {setup.events_live === 1 ? 'event' : 'events'} on sale over USSD · events also get their own code, like *{setup.shortcode}*24#
                                    </p>
                                </>
                            ) : (
                                <>
                                    <p className="mt-2 text-2xl font-extrabold tracking-[-0.03em] sm:text-3xl">Not assigned yet</p>
                                    <p className="mt-3 text-sm text-white/70">
                                        Set USSD_SHORTCODE once the provider assigns the code. Until then no dial codes are shown on event pages.
                                    </p>
                                </>
                            )}
                        </div>
                        <div className="space-y-3 rounded-2xl border border-white/15 bg-white/10 p-4 text-sm backdrop-blur">
                            <div>
                                <p className="text-xs font-semibold text-white/60">Webhook to give the USSD provider</p>
                                <CopyField value={setup.webhook_url} />
                            </div>
                            <p className="flex items-start gap-2 text-xs">
                                {setup.signature_configured ? (
                                    <><ShieldCheck className="h-4 w-4 shrink-0 text-emerald-300" /> Requests are verified with the provider's RSA signature. Unsigned or forged requests are refused.</>
                                ) : (
                                    <><ShieldAlert className="h-4 w-4 shrink-0 text-amber-300" /> No USSD_PUBLIC_KEY is set, so requests are only accepted in local development. Add the provider's public key before going live.</>
                                )}
                            </p>
                        </div>
                    </div>
                </div>
            </Reveal>

            <RevealGroup className="mt-5 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                <RevealItem><StatCard icon={PhoneCall} label="Sessions" value={stats.sessions.toLocaleString()} hint={`${stats.callers.toLocaleString()} unique callers`} tone="brand" /></RevealItem>
                <RevealItem><StatCard icon={Ticket} label="Ticket sales" value={money(stats.ticket_sales_minor, currency)} hint={`${stats.paid_orders} paid of ${stats.orders} orders`} tone="emerald" /></RevealItem>
                <RevealItem><StatCard icon={Users} label="Contributions" value={money(stats.contributions_minor, currency)} hint="Paid over USSD" tone="blue" /></RevealItem>
                <RevealItem><StatCard icon={Hash} label="Direct dials" value={`${percent(stats.direct_dials, stats.sessions)}%`} hint={`${stats.tickets_sent} ticket SMS sent`} tone="amber" /></RevealItem>
            </RevealGroup>

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <Reveal className="lg:col-span-2">
                    <Panel title="Sessions by day" subtitle="Paid orders in green" bodyClassName="pt-4">
                        <div className="flex h-48 items-end gap-2">
                            {daily.map((d) => (
                                <div key={d.date} className="flex h-full flex-1 flex-col items-center justify-end gap-2" title={`${d.sessions} sessions, ${d.paid} paid`}>
                                    <span className="text-[11px] font-semibold text-slate-500">{d.sessions || ''}</span>
                                    <div className="relative w-full max-w-[2.75rem] overflow-hidden rounded-lg bg-brand-500/80" style={{ height: `${Math.max(3, (d.sessions / peak) * 100)}%` }}>
                                        <span className="absolute inset-x-0 bottom-0 bg-emerald-400" style={{ height: `${d.sessions ? (d.paid / d.sessions) * 100 : 0}%` }} />
                                    </div>
                                    <span className="text-[11px] text-slate-400">
                                        {new Date(`${d.date}T00:00:00`).toLocaleDateString('en-US', daily.length > 7 ? { day: 'numeric' } : { weekday: 'short' })}
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Panel>
                </Reveal>

                <Reveal delay={0.05}>
                    <Panel title="Buying funnel" subtitle="Where callers drop off" className="h-full" bodyClassName="pt-3">
                        <ol className="space-y-3">
                            {funnel.map((step, index) => (
                                <li key={step.label}>
                                    <div className="flex items-baseline justify-between text-sm">
                                        <span className="font-semibold text-ink">{step.label}</span>
                                        <span className="text-slate-500">
                                            {step.count.toLocaleString()}
                                            {index > 0 && <span className="ml-1.5 text-xs text-slate-400">{percent(step.count, funnel[0].count)}%</span>}
                                        </span>
                                    </div>
                                    <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-tint/[0.06]">
                                        <div className="h-full rounded-full bg-gradient-to-r from-brand-500 to-emerald-400" style={{ width: `${percent(step.count, funnel[0].count) || (step.count ? 2 : 0)}%` }} />
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </Panel>
                </Reveal>
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <Reveal className="lg:col-span-2">
                    <Panel title="Top events on USSD" bodyClassName="pt-2">
                        {topEvents.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-400">No caller has opened an event yet.</p>
                        ) : (
                            <ul className="divide-y divide-tint/[0.06]">
                                {topEvents.map((e) => (
                                    <li key={e.event_id} className="flex items-center justify-between gap-3 py-3 text-sm">
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-ink">{e.title}</p>
                                            {e.dial && <p className="font-mono text-xs text-brand-600">{e.dial}</p>}
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="font-semibold text-ink">{e.paid} paid</p>
                                            <p className="text-xs text-slate-400">{e.sessions} sessions · {percent(e.paid, e.sessions)}%</p>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </Reveal>

                <Reveal delay={0.05}>
                    <Panel title="Networks" className="h-full" bodyClassName="pt-3">
                        {networks.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-400">No sessions yet.</p>
                        ) : (
                            <ul className="space-y-3">
                                {networks.map((n) => (
                                    <li key={n.network}>
                                        <div className="flex justify-between text-sm">
                                            <span className="flex items-center gap-2 font-semibold text-ink"><Radio className="h-3.5 w-3.5 text-brand-500" /> {n.network}</span>
                                            <span className="text-slate-500">{percent(n.count, stats.sessions)}%</span>
                                        </div>
                                        <div className="mt-1.5 h-2 overflow-hidden rounded-full bg-tint/[0.06]">
                                            <div className="h-full rounded-full bg-brand-500" style={{ width: `${percent(n.count, stats.sessions)}%` }} />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </Reveal>
            </div>

            <Reveal className="mt-5">
                <Panel title="Recent sessions" subtitle="Phone numbers are partly hidden" bodyClassName="pt-2">
                    {recent.length === 0 ? (
                        <EmptyState icon={PhoneCall} title="No USSD sessions yet" body={`When someone dials ${dial}, the session shows up here.`} />
                    ) : (
                        <div className="-mx-2 overflow-x-auto">
                            <table className="w-full min-w-[640px] text-left text-sm">
                                <thead className="text-xs uppercase tracking-wide text-slate-400">
                                    <tr>
                                        <th className="px-2 py-2 font-semibold">Caller</th>
                                        <th className="px-2 py-2 font-semibold">Event</th>
                                        <th className="px-2 py-2 font-semibold">Result</th>
                                        <th className="px-2 py-2 font-semibold">Screens</th>
                                        <th className="px-2 py-2 font-semibold">When</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-tint/[0.06]">
                                    {recent.map((s) => {
                                        const outcome = s.outcome ? outcomes[s.outcome] : null;
                                        return (
                                            <tr key={s.id}>
                                                <td className="px-2 py-2.5">
                                                    <p className="font-mono text-ink">{s.caller}</p>
                                                    <p className="text-xs text-slate-400">{s.network ?? 'Unknown network'}</p>
                                                </td>
                                                <td className="px-2 py-2.5">
                                                    <p className="max-w-[14rem] truncate text-ink">{s.event ?? '—'}</p>
                                                    {s.direct && <p className="text-xs text-brand-600">Dialed event code</p>}
                                                </td>
                                                <td className="px-2 py-2.5">
                                                    {outcome ? (
                                                        <Badge tone={outcome.tone}>{outcome.label}</Badge>
                                                    ) : (
                                                        <span className="text-xs text-slate-500">Left at {steps[s.last_step ?? ''] ?? s.last_step}</span>
                                                    )}
                                                </td>
                                                <td className="px-2 py-2.5 text-slate-500">{s.screens} · {s.duration_seconds}s</td>
                                                <td className="px-2 py-2.5 text-slate-500">{formatDateTime(s.started_at)}</td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </Panel>
            </Reveal>
        </AdminLayout>
    );
}

function CopyField({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);

    function copy() {
        navigator.clipboard?.writeText(value).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    }

    return (
        <button type="button" onClick={copy} className="mt-1 flex w-full items-center justify-between gap-2 rounded-xl bg-black/25 px-3 py-2 text-left font-mono text-xs">
            <span className="truncate">{value}</span>
            {copied ? <Check className="h-4 w-4 shrink-0 text-emerald-300" /> : <Copy className="h-4 w-4 shrink-0 text-white/70" />}
        </button>
    );
}
