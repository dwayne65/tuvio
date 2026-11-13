import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import PublicLayout from '@/Layouts/PublicLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Check, ChevronLeft, CreditCard, Lock, Mail, ShieldCheck, Smartphone, Tag, Ticket, X } from 'lucide-react';
import { FormEvent } from 'react';

interface Line { name: string; quantity: number; unit_price_minor: number; line_total_minor: number }

type PaymentMethod = 'mobile_money' | 'card';

const DIALING_CODE = '256';

/** The local part of a Ugandan number, e.g. "+256 770 123456", "0770123456" → "770123456". */
function localDigits(phone: string): string {
    const digits = phone.replace(/\D/g, '');
    const local = digits.startsWith(DIALING_CODE) ? digits.slice(DIALING_CODE.length) : digits.replace(/^0+/, '');

    return local.slice(0, 9);
}

const methods: { value: PaymentMethod; label: string; hint: string; icon: typeof Smartphone }[] = [
    { value: 'mobile_money', label: 'Mobile Money', hint: 'MTN or Airtel — approve with your PIN', icon: Smartphone },
    { value: 'card', label: 'Card', hint: 'Visa, Mastercard and more', icon: CreditCard },
];

export default function Checkout({ event, lines, subtotal_minor, discount_minor, fee_minor = 0, tax_minor = 0, tax_label = 'VAT', total_minor, promo, buyer, payment_methods, held = false }: PageProps<{
    event: { title: string; slug: string; currency: string };
    lines: Line[];
    subtotal_minor: number;
    discount_minor: number;
    /** Booking fee the buyer pays on top, when the organizer passes the platform fee on. */
    fee_minor?: number;
    tax_minor?: number;
    tax_label?: string;
    total_minor: number;
    promo: string | null;
    buyer: { name: string; email: string; phone: string };
    payment_methods: PaymentMethod[];
    /** Reopened after a failed payment: the order is placed, so its prices and promo are fixed. */
    held?: boolean;
}>) {
    const { app } = usePage<PageProps>().props;
    const { data, setData, post, processing, errors, transform } = useForm({ ...buyer, payment_method: payment_methods[0] });
    const needsPayment = total_minor > 0 && !app.demo_mode;
    const isMobileMoney = data.payment_method === 'mobile_money';
    const promoForm = useForm({ code: '' });

    function applyPromo(e: FormEvent) {
        e.preventDefault();
        promoForm.post('/checkout/promo', { preserveScroll: true, onSuccess: () => promoForm.reset() });
    }
    const count = lines.reduce((n, l) => n + l.quantity, 0);

    function submit(e: FormEvent) {
        e.preventDefault();
        // Free and demo orders skip payment, so no method (and no phone) is required.
        transform((form) => ({ ...form, payment_method: needsPayment ? form.payment_method : null }));
        post('/checkout');
    }

    return (
        <PublicLayout hideTabBar>
            <Head title="Checkout" />
            <div className="container-page max-w-5xl pb-10 pt-3 md:py-12">
                <Link href={`/events/${event.slug}`} className="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-ink">
                    <ChevronLeft className="h-4 w-4" /> Back to event
                </Link>
                <h1 className="mt-3 text-[2rem] font-extrabold tracking-[-0.045em] text-ink md:text-4xl">
                    Almost <span className="font-display font-normal italic tracking-normal text-gradient">there.</span>
                </h1>

                <div className="mt-6 grid gap-5 lg:grid-cols-[1fr_22rem] lg:gap-8">
                    {/* Summary (first on phones) */}
                    <aside className="h-fit overflow-hidden rounded-[1.75rem] border border-tint/[0.08] bg-card/80 lg:order-last lg:sticky lg:top-24">
                        <div className="relative overflow-hidden bg-gradient-to-br from-brand-500 to-brand-800 p-5">
                            <div className="absolute -right-10 -top-12 h-40 w-40 rounded-full bg-white/15 blur-2xl" />
                            <p className="relative flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-[0.16em] text-white/70">
                                <Ticket className="h-3.5 w-3.5" /> {count} {count === 1 ? 'ticket' : 'tickets'}
                            </p>
                            <h2 className="relative mt-2 line-clamp-2 font-display text-[1.9rem] leading-none text-white">{event.title}</h2>
                        </div>
                        <div className="ticket-notch border-t border-dashed border-tint/10 p-5">
                            <div className="space-y-2.5">
                                {lines.map((l, i) => (
                                    <div key={i} className="flex justify-between gap-3 text-sm">
                                        <span className="text-slate-600">{l.quantity} × {l.name}</span>
                                        <span className="font-semibold text-ink">{money(l.line_total_minor, event.currency)}</span>
                                    </div>
                                ))}
                            </div>
                            {promo && (
                                <div className="mt-2.5 flex items-center justify-between gap-3 text-sm">
                                    <span className="inline-flex items-center gap-1.5 font-semibold text-emerald-700">
                                        <Tag className="h-3.5 w-3.5" /> {promo}
                                        {!held && (
                                            <button
                                                type="button"
                                                onClick={() => router.delete('/checkout/promo', { preserveScroll: true })}
                                                className="grid h-5 w-5 place-items-center rounded-full text-slate-400 hover:bg-tint/[0.08] hover:text-ink"
                                                aria-label="Remove promo code"
                                            >
                                                <X className="h-3 w-3" />
                                            </button>
                                        )}
                                    </span>
                                    <span className="font-semibold text-emerald-700">−{money(discount_minor, event.currency)}</span>
                                </div>
                            )}
                            {fee_minor > 0 && (
                                <div className="mt-2.5 flex items-center justify-between gap-3 text-sm">
                                    <span className="text-slate-600">Booking fee</span>
                                    <span className="font-semibold text-ink">{money(fee_minor, event.currency)}</span>
                                </div>
                            )}
                            {tax_minor > 0 && (
                                <div className="mt-2.5 flex items-center justify-between gap-3 text-sm">
                                    <span className="text-slate-600">{tax_label} on booking fee</span>
                                    <span className="font-semibold text-ink">{money(tax_minor, event.currency)}</span>
                                </div>
                            )}
                            <div className="mt-4 flex items-center justify-between border-t border-tint/[0.08] pt-4">
                                <span className="text-sm text-slate-500">Total</span>
                                <span className="text-xl font-extrabold text-ink">{money(total_minor, event.currency)}</span>
                            </div>

                            {!promo && !held && subtotal_minor > 0 && (
                                <form onSubmit={applyPromo} className="mt-4">
                                    <div className="flex items-center gap-2 rounded-full border border-tint/10 bg-canvas p-1 pl-3.5 focus-within:border-brand-400">
                                        <Tag className="h-4 w-4 shrink-0 text-slate-400" />
                                        <input
                                            value={promoForm.data.code}
                                            onChange={(e) => promoForm.setData('code', e.target.value.toUpperCase())}
                                            placeholder="Promo code"
                                            aria-label="Promo code"
                                            className="h-9 min-w-0 flex-1 bg-transparent font-mono text-sm uppercase text-ink placeholder:font-sans placeholder:normal-case placeholder:text-slate-400 focus:outline-none"
                                        />
                                        <button
                                            type="submit"
                                            disabled={promoForm.processing || !promoForm.data.code.trim()}
                                            className="h-9 shrink-0 rounded-full bg-ink px-4 text-sm font-semibold text-canvas disabled:opacity-40"
                                        >
                                            Apply
                                        </button>
                                    </div>
                                    {promoForm.errors.code && <p className="mt-1.5 px-1 text-xs text-danger">{promoForm.errors.code}</p>}
                                </form>
                            )}
                        </div>
                    </aside>

                    <form onSubmit={submit} className="rounded-[1.75rem] border border-tint/[0.08] bg-card/80 p-5 sm:p-7">
                        <h2 className="text-lg font-bold text-ink">Your details</h2>
                        <p className="mt-1 flex items-start gap-2 text-sm text-slate-500">
                            <Mail className="mt-0.5 h-4 w-4 shrink-0 text-brand-300" />
                            Your tickets will be emailed here instantly — no account needed.
                        </p>
                        <div className="mt-5 space-y-4">
                            <Field label="Full name" error={errors.name}>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="name" required />
                            </Field>
                            <Field label="Email" error={errors.email}>
                                <Input type="email" inputMode="email" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="email" required />
                            </Field>
                            <Field label={needsPayment && isMobileMoney ? 'Mobile Money number' : 'Phone (optional)'} error={errors.phone}>
                                <div className="relative">
                                    <span className="pointer-events-none absolute inset-y-0 left-4 flex items-center text-[15px] font-semibold text-ink">
                                        +{DIALING_CODE}
                                    </span>
                                    <Input
                                        type="tel"
                                        inputMode="numeric"
                                        value={localDigits(data.phone)}
                                        onChange={(e) => {
                                            const local = localDigits(e.target.value);
                                            setData('phone', local ? `+${DIALING_CODE}${local}` : '');
                                        }}
                                        autoComplete="tel-national"
                                        placeholder="770 123456"
                                        className="pl-[4.25rem]"
                                        required={needsPayment && isMobileMoney}
                                    />
                                </div>
                            </Field>
                        </div>

                        {needsPayment && (
                            <fieldset className="mt-7">
                                <legend className="text-lg font-bold text-ink">How would you like to pay?</legend>
                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                    {methods.map(({ value, label, hint, icon: Icon }) => {
                                        const selected = data.payment_method === value;
                                        // Shown but not selectable until the provider can route it.
                                        const available = payment_methods.includes(value);

                                        return (
                                            <label
                                                key={value}
                                                aria-disabled={!available}
                                                className={`relative flex items-center gap-3 rounded-2xl border p-4 transition-colors ${
                                                    !available
                                                        ? 'cursor-not-allowed border-tint/10 opacity-50'
                                                        : selected
                                                          ? 'cursor-pointer border-brand-500 bg-brand-500/[0.07] ring-1 ring-brand-500'
                                                          : 'cursor-pointer border-tint/10 hover:border-tint/20'
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="payment_method"
                                                    value={value}
                                                    checked={selected}
                                                    disabled={!available}
                                                    onChange={() => setData('payment_method', value)}
                                                    className="sr-only"
                                                />
                                                <span className={`grid h-10 w-10 shrink-0 place-items-center rounded-full ${selected ? 'bg-brand-500 text-white' : 'bg-tint/[0.06] text-slate-500'}`}>
                                                    <Icon className="h-5 w-5" />
                                                </span>
                                                <span className="min-w-0">
                                                    <span className="block font-semibold text-ink">{label}</span>
                                                    <span className="block text-xs text-slate-500">{available ? hint : 'Coming soon'}</span>
                                                </span>
                                                {selected && <Check className="absolute right-3 top-3 h-4 w-4 text-brand-500" />}
                                            </label>
                                        );
                                    })}
                                </div>
                                {errors.payment_method && <p className="mt-1.5 text-xs text-danger">{errors.payment_method}</p>}
                            </fieldset>
                        )}
                        <Button type="submit" size="lg" className="mt-7 w-full" disabled={processing}>
                            <Lock className="h-4 w-4" />{' '}
                            {app.demo_mode
                                ? 'Complete demo payment'
                                : total_minor > 0
                                  ? `Pay ${money(total_minor, event.currency)} ${isMobileMoney ? 'with Mobile Money' : 'by card'}`
                                  : 'Get my free tickets'}
                        </Button>
                        <p className="mt-3 flex items-center justify-center gap-1.5 text-center text-xs text-slate-400">
                            <ShieldCheck className="h-3.5 w-3.5" />
                            {app.demo_mode
                                ? 'Demo mode: completes instantly, no real money is charged.'
                                : !needsPayment
                                  ? 'No payment needed.'
                                  : isMobileMoney
                                    ? 'You will get a prompt on your phone to enter your Mobile Money PIN.'
                                    : 'You will enter your card details on our secure payment page.'}
                        </p>
                    </form>
                </div>
            </div>
        </PublicLayout>
    );
}
