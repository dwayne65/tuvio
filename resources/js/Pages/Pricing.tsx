import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import PublicLayout from '@/Layouts/PublicLayout';
import { cn, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, usePage } from '@inertiajs/react';
import { Check, ChevronDown, Lock, ShieldCheck, Smartphone, Sparkles, Zap } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';

export interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: { minor: number; currency: string };
    commission_bps: number;
    interval: string;
    event_limit: number | null;
    lifetime_event_limit: boolean;
    ticket_limit: number | null;
    team_member_limit: number | null;
    ussd_event_limit: number | null;
    capabilities: string[];
    features: string[];
}

type Capabilities = Record<string, string>;

const POPULAR = 'professional';

export function rate(bps: number): string {
    return `${bps / 100}%`;
}

function limit(value: number | null, unit: string): string {
    return value === null ? `Unlimited ${unit}` : `${value.toLocaleString()} ${unit}`;
}

function eventLimit(plan: Plan): string {
    if (plan.event_limit === null) return 'Unlimited';
    return plan.lifetime_event_limit ? `${plan.event_limit} ${plan.event_limit === 1 ? 'event' : 'events'} in total` : `${plan.event_limit.toLocaleString()} live at a time`;
}

/** Fee plus the tax charged on it, as a fraction of the ticket price. */
function effectiveRate(plan: Plan, taxBps: number): number {
    return (plan.commission_bps / 10000) * (1 + taxBps / 10000);
}

/**
 * Monthly ticket sales above which a paid plan's lower fee more than covers its price,
 * compared with the free plan that has the most features. This is the anchor that
 * makes Enterprise an easy decision for busy organizers.
 */
export function breakEvenSales(paid: Plan, plans: Plan[], taxBps: number): number | null {
    const free = [...plans].reverse().find((p) => p.price.minor === 0);
    if (!free || paid.price.minor === 0) return null;
    const saving = effectiveRate(free, taxBps) - effectiveRate(paid, taxBps);
    return saving > 0 ? Math.ceil(paid.price.minor / saving) : null;
}

export default function Pricing({ plans, capabilities, currency }: PageProps<{ plans: Plan[]; capabilities: Capabilities; currency: string }>) {
    const { app } = usePage<PageProps>().props;

    return (
        <PublicLayout>
            <Head title="Pricing" />

            <section className="container-page pb-6 pt-16 text-center">
                <span className="inline-flex items-center gap-2 rounded-full border border-tint/10 bg-tint/[0.04] px-3.5 py-1.5 text-xs font-semibold text-slate-500">
                    <Sparkles className="h-3.5 w-3.5 text-brand-500" /> No monthly fee to get started
                </span>
                <h1 className="mx-auto mt-5 max-w-3xl text-4xl font-extrabold tracking-tight text-ink sm:text-5xl">
                    You only pay when you <span className="text-brand-500">sell</span>
                </h1>
                <p className="mx-auto mt-4 max-w-2xl text-slate-500">
                    Get every growth tool on Professional for free. A small fee comes out of each paid ticket{app.tax.bps > 0 ? `, plus ${app.tax.label}` : ', with no tax added'}. Selling a lot? Enterprise has the lowest
                    fee on TUVIO. Free tickets are always free.
                </p>
                <TrustStrip />
            </section>

            <section className="container-page py-10">
                <div className="mx-auto grid max-w-6xl gap-6 lg:grid-cols-3">
                    {plans.map((plan) => (
                        <PlanCard key={plan.id} plan={plan} plans={plans} />
                    ))}
                </div>
            </section>

            <section className="container-page py-10">
                <SavingsCalculator plans={plans} currency={currency} />
            </section>

            <section className="container-page py-10">
                <ComparisonTable plans={plans} capabilities={capabilities} />
            </section>

            <section className="container-page pb-20 pt-10">
                <Faq plans={plans} />
            </section>
        </PublicLayout>
    );
}

function TrustStrip() {
    const items = [
        { icon: ShieldCheck, label: 'Payments secured by Paytota' },
        { icon: Smartphone, label: 'Mobile Money and cards' },
        { icon: Zap, label: 'Plan active the moment payment clears' },
    ];

    return (
        <ul className="mx-auto mt-8 flex max-w-3xl flex-wrap justify-center gap-x-6 gap-y-2 text-sm text-slate-500">
            {items.map(({ icon: Icon, label }) => (
                <li key={label} className="inline-flex items-center gap-2">
                    <Icon className="h-4 w-4 text-brand-500" /> {label}
                </li>
            ))}
        </ul>
    );
}

/** The action for a plan: guests register, organizers pay for paid plans on Paytota's checkout. */
function PlanAction({ plan, popular }: { plan: Plan; popular: boolean }) {
    const { auth } = usePage<PageProps>().props;
    const currentSlug = auth.currentOrganization?.plan?.slug;
    const free = plan.price.minor === 0;
    const variant = popular ? 'primary' : 'outline';

    if (!auth.user) {
        return (
            <Button href="/register" variant={variant} className="w-full">
                {free ? 'Start for free' : `Get ${plan.name}`}
            </Button>
        );
    }

    if (!auth.currentOrganization) {
        return (
            <Button href="/organizations/new" variant={variant} className="w-full">
                Create your organization
            </Button>
        );
    }

    if (currentSlug === plan.slug) {
        return (
            <Button href="/dashboard/subscription" variant="outline" className="w-full">
                <Check className="h-4 w-4" /> Your current plan
            </Button>
        );
    }

    if (free) {
        return (
            <Button href="/dashboard/subscription" variant={variant} className="w-full">
                Manage plan
            </Button>
        );
    }

    return (
        <Button href={`/dashboard/subscription?plan=${plan.slug}`} variant={variant} className="w-full">
            <Lock className="h-4 w-4" /> Upgrade for {money(plan.price.minor, plan.price.currency)}
        </Button>
    );
}

export function PlanCard({ plan, action, plans }: { plan: Plan; action?: ReactNode; plans?: Plan[] }) {
    const { app } = usePage<PageProps>().props;
    const popular = plan.slug === POPULAR;
    const free = plan.price.minor === 0;
    const breakEven = plans ? breakEvenSales(plan, plans, app.tax.bps) : null;

    return (
        <div
            className={cn(
                'relative flex flex-col rounded-[1.75rem] border p-7 text-left shadow-[var(--shadow-card)]',
                popular ? 'border-brand-500/60 bg-gradient-to-b from-brand-500/10 to-card ring-1 ring-brand-500/30' : 'border-tint/[0.08] bg-card',
            )}
        >
            {popular && (
                <span className="absolute -top-3 left-7 rounded-full bg-brand-500 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white">
                    Most popular · Free
                </span>
            )}
            <h3 className="text-lg font-bold text-ink">{plan.name}</h3>
            {plan.description && <p className="mt-1 min-h-[2.5rem] text-sm text-slate-500">{plan.description}</p>}

            <div className="mt-5 flex items-baseline gap-1">
                <span className="text-4xl font-extrabold tracking-tight text-ink">{free ? 'Free' : money(plan.price.minor, plan.price.currency)}</span>
                {!free && <span className="text-sm text-slate-500">/{plan.interval === 'yearly' ? 'year' : 'month'}</span>}
            </div>

            <div className="mt-4 flex items-center justify-between rounded-2xl bg-tint/[0.04] px-4 py-3">
                <span className="text-sm text-slate-500">Fee per paid ticket</span>
                <span className="text-right">
                    <span className="text-xl font-extrabold text-brand-500">{rate(plan.commission_bps)}</span>
                    <span className="block text-[11px] font-semibold text-slate-400">{app.tax.bps > 0 ? `+ ${app.tax.label} on the fee` : 'No tax added'}</span>
                </span>
            </div>
            {plan.lifetime_event_limit && (
                <p className="mt-3 rounded-xl bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-700">
                    Ends after your first event. Then move to Professional, still free.
                </p>
            )}
            {breakEven !== null && (
                <p className="mt-3 rounded-xl bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-700">
                    Cheaper than Professional once you sell over {money(breakEven, plan.price.currency)} a month.
                </p>
            )}

            <ul className="mt-6 flex-1 space-y-3">
                {plan.features.map((feature) => (
                    <li key={feature} className="flex items-start gap-2.5 text-sm text-slate-600">
                        <span className="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full bg-brand-500/15">
                            <Check className="h-3 w-3 text-brand-600" />
                        </span>
                        {feature}
                    </li>
                ))}
            </ul>

            <div className="mt-8">{action ?? <PlanAction plan={plan} popular={popular} />}</div>
        </div>
    );
}

/** Monthly cost of each plan for the organizer's own numbers, so the best value is obvious. */
function SavingsCalculator({ plans, currency }: { plans: Plan[]; currency: string }) {
    const { app } = usePage<PageProps>().props;
    const [price, setPrice] = useState('50000');
    const [tickets, setTickets] = useState('300');

    const priceMinor = Math.max(0, Math.round(Number(price.replace(/[^\d.]/g, '')) || 0)) * 100;
    const count = Math.max(0, Math.floor(Number(tickets.replace(/[^\d]/g, '')) || 0));

    const costs = useMemo(
        () =>
            plans.map((plan) => {
                const fee = Math.round((priceMinor * plan.commission_bps) / 10000);
                const fees = (fee + Math.round((fee * app.tax.bps) / 10000)) * count;
                const withinLimit = plan.ticket_limit === null || count <= plan.ticket_limit;

                return { plan, fees, total: fees + plan.price.minor, withinLimit };
            }),
        [plans, priceMinor, count, app.tax.bps],
    );
    const eligible = costs.filter((c) => c.withinLimit);
    const best = eligible.length ? eligible.reduce((a, b) => (b.total < a.total ? b : a)) : null;

    return (
        <div className="mx-auto max-w-5xl rounded-[1.75rem] border border-tint/[0.08] bg-card p-6 shadow-[var(--shadow-card)] sm:p-8">
            <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div className="max-w-md">
                    <h2 className="text-2xl font-extrabold tracking-tight text-ink">Which plan pays for itself?</h2>
                    <p className="mt-2 text-sm text-slate-500">
                        Enter a typical month. We add the plan price to the per-ticket fee{app.tax.bps > 0 ? ` and its ${app.tax.label}` : ''} so you see your real cost on each plan.
                    </p>
                </div>
                <div className="grid gap-4 sm:grid-cols-2 lg:w-[26rem]">
                    <label className="block text-sm font-semibold text-ink">
                        Average ticket price ({currency})
                        <Input className="mt-1.5" inputMode="numeric" value={price} onChange={(e) => setPrice(e.target.value)} />
                    </label>
                    <label className="block text-sm font-semibold text-ink">
                        Tickets sold per month
                        <Input className="mt-1.5" inputMode="numeric" value={tickets} onChange={(e) => setTickets(e.target.value)} />
                    </label>
                </div>
            </div>

            <div className="mt-8 grid gap-3 sm:grid-cols-3">
                {costs.map(({ plan, fees, total, withinLimit }) => {
                    const isBest = best?.plan.id === plan.id;

                    return (
                        <div
                            key={plan.id}
                            className={cn('rounded-2xl p-5', isBest ? 'bg-brand-500 text-white' : 'bg-tint/[0.04]', !withinLimit && 'opacity-60')}
                        >
                            <div className="flex items-center justify-between">
                                <span className={cn('text-sm font-bold', isBest ? 'text-white' : 'text-ink')}>{plan.name}</span>
                                {isBest && <span className="rounded-full bg-white/20 px-2 py-0.5 text-[11px] font-bold">Best value</span>}
                            </div>
                            <p className={cn('mt-3 text-2xl font-extrabold', isBest ? 'text-white' : 'text-ink')}>{money(total, currency)}</p>
                            <p className={cn('mt-1 text-xs', isBest ? 'text-white/80' : 'text-slate-500')}>
                                {withinLimit
                                    ? `${money(fees, currency)} in fees${app.tax.bps > 0 ? ` and ${app.tax.label}` : ''} + ${plan.price.minor === 0 ? 'no plan cost' : `${money(plan.price.minor, currency)} plan`}`
                                    : `Limited to ${plan.ticket_limit?.toLocaleString()} tickets per event`}
                            </p>
                        </div>
                    );
                })}
            </div>
            <p className="mt-4 text-xs text-slate-500">
                You choose per event whether the fee comes out of your earnings or is added for buyers at checkout. Mobile Money and card charges are included.
            </p>
        </div>
    );
}

function ComparisonTable({ plans, capabilities }: { plans: Plan[]; capabilities: Capabilities }) {
    const taxBps = usePage<PageProps>().props.app.tax.bps;
    const rows: { label: string; value: (plan: Plan) => ReactNode }[] = [
        {
            label: 'Fee per paid ticket',
            value: (p) => (
                <span className="font-bold text-brand-500">
                    {rate(p.commission_bps)} {taxBps > 0 && <span className="text-xs font-semibold text-slate-400">+ tax</span>}
                </span>
            ),
        },
        { label: 'Plan price', value: (p) => (p.price.minor === 0 ? 'Free' : `${money(p.price.minor, p.price.currency)} / month`) },
        { label: 'Events', value: (p) => eventLimit(p) },
        { label: 'Tickets per event', value: (p) => limit(p.ticket_limit, 'tickets') },
        { label: 'Team seats', value: (p) => limit(p.team_member_limit, p.team_member_limit === 1 ? 'seat' : 'seats') },
        { label: 'QR tickets and gate check-in', value: () => <Yes /> },
        { label: 'Mobile Money and card payments', value: () => <Yes /> },
        { label: 'USSD ticket sales', value: (p) => (p.ussd_event_limit === 0 ? <No /> : limit(p.ussd_event_limit, 'events')) },
        ...Object.entries(capabilities).map(([key, label]) => ({
            label,
            value: (p: Plan) => (p.capabilities.includes(key) ? <Yes /> : <No />),
        })),
    ];

    return (
        <div className="mx-auto max-w-5xl">
            <h2 className="text-center text-2xl font-extrabold tracking-tight text-ink">Compare every plan</h2>
            <div className="mt-8 overflow-x-auto rounded-[1.75rem] border border-tint/[0.08] bg-card shadow-[var(--shadow-card)]">
                <table className="w-full min-w-[40rem] text-left text-sm">
                    <thead>
                        <tr className="border-b border-tint/[0.08]">
                            <th className="px-5 py-4 font-semibold text-slate-500">Features</th>
                            {plans.map((plan) => (
                                <th key={plan.id} className={cn('px-5 py-4 font-bold text-ink', plan.slug === POPULAR && 'bg-brand-500/[0.06]')}>
                                    {plan.name}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.label} className="border-b border-tint/[0.05] last:border-0">
                                <td className="px-5 py-3.5 text-slate-600">{row.label}</td>
                                {plans.map((plan) => (
                                    <td key={plan.id} className={cn('px-5 py-3.5 text-ink', plan.slug === POPULAR && 'bg-brand-500/[0.06]')}>
                                        {row.value(plan)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function Yes() {
    return <Check className="h-4 w-4 text-brand-500" aria-label="Included" />;
}

function No() {
    return <span className="text-slate-300" aria-label="Not included">—</span>;
}

function Faq({ plans }: { plans: Plan[] }) {
    const taxBps = usePage<PageProps>().props.app.tax.bps;
    const starter = plans.find((p) => p.price.minor === 0);
    const items = [
        {
            q: 'How do I pay for a plan?',
            a: 'Choose a plan from your dashboard and you are taken to Paytota’s secure checkout, where you pay by Mobile Money or card. Your plan switches on as soon as Paytota confirms the payment.',
        },
        {
            q: 'Can I use a paid plan before paying?',
            a: 'No. Paid features, lower fees and higher limits only unlock after your payment is verified. Until then you stay on your current plan.',
        },
        {
            q: 'What happens when my month ends?',
            a: `Renew from your dashboard to keep your plan. Renewing early adds a full month after your current one ends. If you don’t renew, you move back to ${starter?.name ?? 'the free plan'} and its limits.`,
        },
        {
            q: 'Can I cancel?',
            a: 'Yes, at any time. You keep your paid plan until the end of the month you paid for, then move to the free plan.',
        },
        {
            q: 'Is Professional really free?',
            a: `Yes. There is no monthly fee. We take a small fee from each paid ticket${taxBps > 0 ? ', plus tax on that fee' : ''}, so you only pay when you sell.`,
        },
        {
            q: `What is ${starter?.name ?? 'Starter'} for?`,
            a: `${starter?.name ?? 'Starter'} lets you try TUVIO with one event. After that event, switch to Professional in one click. It costs nothing and unlocks analytics, discounts, promoters and a team.`,
        },
        ...(taxBps > 0
            ? [
                  {
                      q: 'Why is there tax on the fee?',
                      a: 'Our fee is a service, so government tax (VAT) applies to it. It is charged on the fee only, never on your ticket price, and we pay it to the tax authority.',
                  },
              ]
            : []),
        {
            q: 'Do you charge for free tickets?',
            a: 'Never. The per-ticket fee only applies to paid tickets, and Mobile Money and card charges are already included in it.',
        },
    ];

    return (
        <div className="mx-auto max-w-3xl">
            <h2 className="text-center text-2xl font-extrabold tracking-tight text-ink">Questions, answered</h2>
            <div className="mt-8 divide-y divide-tint/[0.08] rounded-[1.75rem] border border-tint/[0.08] bg-card shadow-[var(--shadow-card)]">
                {items.map((item) => (
                    <details key={item.q} className="group px-6 py-5">
                        <summary className="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-ink">
                            {item.q}
                            <ChevronDown className="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-180" />
                        </summary>
                        <p className="mt-3 text-sm leading-relaxed text-slate-500">{item.a}</p>
                    </details>
                ))}
            </div>
        </div>
    );
}
