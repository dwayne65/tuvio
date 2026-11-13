import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Accent } from '@/Components/app/Kit';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, money } from '@/lib/utils';
import { Plan, PlanCard, rate } from '@/Pages/Pricing';
import { PageProps } from '@/types';
import { Input } from '@/Components/ui/Input';
import { cn } from '@/lib/utils';
import { Head, router, usePage } from '@inertiajs/react';
import { Check, CreditCard, Lock, ShieldCheck, Smartphone, X } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface Current {
    plan_id: number | null;
    plan_name: string;
    commission_bps: number;
    status: string | null;
    is_paid: boolean;
    current_period_end: string | null;
    cancel_at_period_end: boolean;
}

interface Usage {
    events: number;
    event_limit: number | null;
    team: number;
    team_limit: number | null;
}

interface Invoice {
    number: string;
    plan_name: string;
    amount_minor: number;
    currency: string;
    paid_at?: string | null;
    period_end?: string | null;
}

type PaymentMethod = 'mobile_money' | 'card';

/** What the payment panel is paying for: a plan to switch to, or an invoice already open. */
type Checkout = { title: string; amount_minor: number; currency: string; url: string; data: Record<string, unknown> };

export default function Subscription({ plans, current, usage, pending_invoice, invoices, can_manage, payment_methods, default_phone }: PageProps<{
    plans: Plan[];
    current: Current;
    usage: Usage;
    pending_invoice: Invoice | null;
    invoices: Invoice[];
    can_manage: boolean;
    payment_methods: PaymentMethod[];
    default_phone: string;
}>) {
    const [busyPlan, setBusyPlan] = useState<number | null>(null);
    const [checkout, setCheckout] = useState<Checkout | null>(null);
    const currentPlan = plans.find((p) => p.id === current.plan_id);

    function checkoutFor(plan: Plan): Checkout {
        return { title: `${plan.name} plan`, amount_minor: plan.price.minor, currency: plan.price.currency, url: '/dashboard/subscription', data: { plan_id: plan.id } };
    }

    function choose(plan: Plan) {
        if (plan.price.minor > 0) {
            setCheckout(checkoutFor(plan));

            return;
        }
        router.post('/dashboard/subscription', { plan_id: plan.id }, { onStart: () => setBusyPlan(plan.id), onFinish: () => setBusyPlan(null) });
    }

    // Arriving from the public pricing page with ?plan=slug opens that plan's payment panel.
    useEffect(() => {
        const slug = new URLSearchParams(window.location.search).get('plan');
        const plan = plans.find((p) => p.slug === slug && p.id !== current.plan_id && p.price.minor > 0);
        if (plan && can_manage) {
            setCheckout(checkoutFor(plan));
        }
    }, []);

    return (
        <AppLayout title={<>Your <Accent>plan</Accent></>} subtitle="Lower your fee per ticket and unlock more tools.">
            <Head title="Subscription" />

            <div className="grid gap-4 lg:grid-cols-3">
                <div className="rounded-[1.5rem] border border-tint/[0.07] bg-card p-6 shadow-[var(--shadow-card)] lg:col-span-2">
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-xl font-bold text-ink">{current.plan_name}</h2>
                        {current.is_paid && <Badge tone={current.cancel_at_period_end ? 'warning' : 'success'}>{current.cancel_at_period_end ? 'Ending' : 'Active'}</Badge>}
                        {!current.is_paid && <Badge tone="neutral">Free</Badge>}
                    </div>
                    <p className="mt-1 text-sm text-slate-500">
                        {current.is_paid && current.current_period_end
                            ? current.cancel_at_period_end
                                ? `Ends ${formatDate(current.current_period_end)}, then you move to the free plan.`
                                : `Paid until ${formatDate(current.current_period_end)}. Renew any time to add another month.`
                            : 'You are on the free plan. Upgrade to lower your fee and unlock paid features.'}
                    </p>

                    <div className="mt-5 grid gap-3 sm:grid-cols-3">
                        <Stat label="Fee per paid ticket" value={rate(current.commission_bps)} highlight />
                        <Stat label={currentPlan?.lifetime_event_limit ? 'Free events used' : 'Live events'} value={usage.event_limit === null ? `${usage.events} / Unlimited` : `${usage.events} / ${usage.event_limit}`} meter={meter(usage.events, usage.event_limit)} />
                        <Stat label="Team seats" value={usage.team_limit === null ? `${usage.team} / Unlimited` : `${usage.team} / ${usage.team_limit}`} meter={meter(usage.team, usage.team_limit)} />
                    </div>

                    {can_manage && current.is_paid && (
                        <div className="mt-5 flex flex-wrap gap-2">
                            {currentPlan && currentPlan.price.minor > 0 && (
                                <Button onClick={() => choose(currentPlan)} disabled={busyPlan !== null}>
                                    <CreditCard className="h-4 w-4" /> Renew {currentPlan.name}
                                </Button>
                            )}
                            {current.cancel_at_period_end ? (
                                <Button variant="outline" onClick={() => router.post('/dashboard/subscription/resume')}>
                                    Keep my plan
                                </Button>
                            ) : (
                                <Button variant="outline" onClick={() => router.post('/dashboard/subscription/cancel')}>
                                    Cancel at period end
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex flex-col justify-between gap-4 rounded-[1.5rem] border border-tint/[0.07] bg-card p-6 shadow-[var(--shadow-card)]">
                    {pending_invoice ? (
                        <div>
                            <p className="text-sm font-semibold text-ink">Payment not completed</p>
                            <p className="mt-1 text-sm text-slate-500">
                                {pending_invoice.plan_name} is waiting for payment of {money(pending_invoice.amount_minor, pending_invoice.currency)}. It
                                switches on as soon as the payment clears.
                            </p>
                            {can_manage && (
                                <Button
                                    className="mt-4 w-full"
                                    onClick={() =>
                                        setCheckout({
                                            title: `${pending_invoice.plan_name} plan`,
                                            amount_minor: pending_invoice.amount_minor,
                                            currency: pending_invoice.currency,
                                            url: `/dashboard/subscription/invoices/${pending_invoice.number}/pay`,
                                            data: {},
                                        })
                                    }
                                >
                                    <Lock className="h-4 w-4" /> Complete payment
                                </Button>
                            )}
                        </div>
                    ) : (
                        <div>
                            <ShieldCheck className="h-6 w-6 text-brand-500" />
                            <p className="mt-3 text-sm font-semibold text-ink">Secure payments by Paytota</p>
                            <p className="mt-1 text-sm text-slate-500">Pay by Mobile Money or card. Your plan only unlocks once the payment is verified.</p>
                        </div>
                    )}
                    {!can_manage && <p className="text-xs text-slate-500">Only owners and finance admins can change the plan.</p>}
                </div>
            </div>

            <div className="mt-8 grid gap-5 lg:grid-cols-3">
                {plans.map((plan) => (
                    <PlanCard
                        key={plan.id}
                        plan={plan}
                        plans={plans}
                        action={
                            plan.id === current.plan_id ? (
                                <Button variant="outline" className="w-full" disabled>
                                    <Check className="h-4 w-4" /> Current plan
                                </Button>
                            ) : !can_manage ? null : plan.price.minor === 0 ? (
                                <Button variant="outline" className="w-full" onClick={() => choose(plan)} disabled={busyPlan !== null}>
                                    Switch to {plan.name} for free
                                </Button>
                            ) : (
                                <Button variant={plan.slug === 'professional' ? 'primary' : 'outline'} className="w-full" onClick={() => choose(plan)} disabled={busyPlan !== null}>
                                    <Lock className="h-4 w-4" />
                                    {busyPlan === plan.id ? 'Opening secure checkout...' : `Pay ${money(plan.price.minor, plan.price.currency)} and upgrade`}
                                </Button>
                            )
                        }
                    />
                ))}
            </div>

            {invoices.length > 0 && (
                <div className="mt-8 overflow-x-auto rounded-[1.5rem] border border-tint/[0.07] bg-card shadow-[var(--shadow-card)]">
                    <h3 className="px-6 pt-5 text-base font-bold text-ink">Payment history</h3>
                    <table className="mt-3 w-full min-w-[32rem] text-left text-sm">
                        <thead>
                            <tr className="border-b border-tint/[0.08] text-slate-500">
                                <th className="px-6 py-3 font-semibold">Invoice</th>
                                <th className="px-6 py-3 font-semibold">Plan</th>
                                <th className="px-6 py-3 font-semibold">Amount</th>
                                <th className="px-6 py-3 font-semibold">Paid</th>
                                <th className="px-6 py-3 font-semibold">Covers until</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.map((invoice) => (
                                <tr key={invoice.number} className="border-b border-tint/[0.05] last:border-0">
                                    <td className="px-6 py-3 font-mono text-xs text-ink">{invoice.number}</td>
                                    <td className="px-6 py-3 text-ink">{invoice.plan_name}</td>
                                    <td className="px-6 py-3 text-ink">{money(invoice.amount_minor, invoice.currency)}</td>
                                    <td className="px-6 py-3 text-slate-500">{invoice.paid_at ? formatDate(invoice.paid_at) : '—'}</td>
                                    <td className="px-6 py-3 text-slate-500">{invoice.period_end ? formatDate(invoice.period_end) : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {checkout && (
                <PaymentPanel checkout={checkout} methods={payment_methods} defaultPhone={default_phone} onClose={() => setCheckout(null)} />
            )}
        </AppLayout>
    );
}

/** Choose Mobile Money (a PIN prompt to this phone) or card (Paytota's secure checkout page). */
function PaymentPanel({ checkout, methods, defaultPhone, onClose }: { checkout: Checkout; methods: PaymentMethod[]; defaultPhone: string; onClose: () => void }) {
    const { errors } = usePage<PageProps>().props;
    const [method, setMethod] = useState<PaymentMethod>(methods[0] ?? 'card');
    const [phone, setPhone] = useState(defaultPhone);
    const [busy, setBusy] = useState(false);

    function submit(event: FormEvent) {
        event.preventDefault();
        router.post(
            checkout.url,
            { ...checkout.data, payment_method: method, phone: method === 'mobile_money' ? phone : null },
            { preserveScroll: true, onStart: () => setBusy(true), onFinish: () => setBusy(false) },
        );
    }

    const options: { value: PaymentMethod; label: string; hint: string; icon: typeof Smartphone }[] = [
        { value: 'mobile_money', label: 'Mobile Money', hint: 'MTN or Airtel. Approve with your PIN.', icon: Smartphone },
        { value: 'card', label: 'Card', hint: 'Visa or Mastercard on a secure page.', icon: CreditCard },
    ];

    return (
        <div className="fixed inset-0 z-[70] flex items-end justify-center bg-black/50 p-4 backdrop-blur-sm sm:items-center" role="dialog" aria-modal="true" aria-label="Pay for your plan">
            <form onSubmit={submit} className="w-full max-w-md rounded-[1.75rem] border border-tint/[0.08] bg-card p-6 shadow-2xl">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-slate-500">{checkout.title}</p>
                        <p className="text-2xl font-extrabold text-ink">{money(checkout.amount_minor, checkout.currency)}</p>
                    </div>
                    <button type="button" onClick={onClose} className="grid h-9 w-9 place-items-center rounded-full bg-tint/[0.06]" aria-label="Close">
                        <X className="h-4 w-4" />
                    </button>
                </div>

                <div className="mt-5 grid gap-3">
                    {options.filter((o) => methods.includes(o.value)).map(({ value, label, hint, icon: Icon }) => (
                        <label
                            key={value}
                            className={cn(
                                'flex cursor-pointer items-center gap-3 rounded-2xl border p-4',
                                method === value ? 'border-brand-500 bg-brand-500/[0.07] ring-1 ring-brand-500' : 'border-tint/10 hover:border-tint/20',
                            )}
                        >
                            <input type="radio" name="payment_method" value={value} checked={method === value} onChange={() => setMethod(value)} className="sr-only" />
                            <span className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-full', method === value ? 'bg-brand-500 text-white' : 'bg-tint/[0.06] text-slate-500')}>
                                <Icon className="h-4 w-4" />
                            </span>
                            <span>
                                <span className="block font-semibold text-ink">{label}</span>
                                <span className="block text-xs text-slate-500">{hint}</span>
                            </span>
                        </label>
                    ))}
                </div>

                {method === 'mobile_money' && (
                    <label className="mt-4 block text-sm font-semibold text-ink">
                        Mobile Money number
                        <Input className="mt-1.5" inputMode="tel" autoComplete="tel" placeholder="0770 123456" value={phone} onChange={(e) => setPhone(e.target.value)} />
                    </label>
                )}
                {(errors.phone || errors.payment_method) && <p className="mt-2 text-sm text-danger">{errors.phone ?? errors.payment_method}</p>}

                <Button type="submit" className="mt-5 w-full" disabled={busy}>
                    <Lock className="h-4 w-4" />
                    {busy ? 'Starting payment...' : method === 'mobile_money' ? 'Send payment prompt' : 'Continue to secure checkout'}
                </Button>
                <p className="mt-3 text-center text-xs text-slate-500">Secured by Paytota. Your plan switches on once the payment is confirmed.</p>
            </form>
        </div>
    );
}

function meter(used: number, max: number | null): number | null {
    return max === null || max === 0 ? null : Math.min(100, Math.round((used / max) * 100));
}

function Stat({ label, value, highlight = false, meter: percent = null }: { label: string; value: string; highlight?: boolean; meter?: number | null }) {
    return (
        <div className={highlight ? 'rounded-2xl bg-brand-500 p-4 text-white' : 'rounded-2xl bg-tint/[0.04] p-4'}>
            <p className={highlight ? 'text-xs text-white/75' : 'text-xs text-slate-500'}>{label}</p>
            <p className={highlight ? 'mt-1 text-xl font-extrabold text-white' : 'mt-1 text-xl font-extrabold text-ink'}>{value}</p>
            {percent !== null && (
                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-tint/[0.08]">
                    <div className={percent >= 100 ? 'h-full rounded-full bg-danger' : 'h-full rounded-full bg-brand-500'} style={{ width: `${percent}%` }} />
                </div>
            )}
        </div>
    );
}

