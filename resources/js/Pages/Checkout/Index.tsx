import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import PublicLayout from '@/Layouts/PublicLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, Lock, Mail, ShieldCheck, Ticket } from 'lucide-react';
import { FormEvent } from 'react';

interface Line { name: string; quantity: number; unit_price_minor: number; line_total_minor: number }

export default function Checkout({ event, lines, subtotal_minor, buyer }: PageProps<{
    event: { title: string; slug: string; currency: string };
    lines: Line[];
    subtotal_minor: number;
    buyer: { name: string; email: string; phone: string };
}>) {
    const { flash, app } = usePage<PageProps>().props;
    const { data, setData, post, processing, errors } = useForm(buyer);
    const count = lines.reduce((n, l) => n + l.quantity, 0);

    function submit(e: FormEvent) {
        e.preventDefault();
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

                {flash.error && (
                    <div className="mt-4 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm text-rose-700 dark:text-rose-300">{flash.error}</div>
                )}

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
                            <div className="mt-4 flex items-center justify-between border-t border-tint/[0.08] pt-4">
                                <span className="text-sm text-slate-500">Total</span>
                                <span className="text-xl font-extrabold text-ink">{money(subtotal_minor, event.currency)}</span>
                            </div>
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
                            <Field label="Phone (for Mobile Money)" error={errors.phone}>
                                <Input type="tel" inputMode="tel" value={data.phone} onChange={(e) => setData('phone', e.target.value)} autoComplete="tel" placeholder="+256…" />
                            </Field>
                        </div>
                        <Button type="submit" size="lg" className="mt-7 w-full" disabled={processing}>
                            <Lock className="h-4 w-4" /> {app.demo_mode ? 'Complete demo payment' : `Pay ${money(subtotal_minor, event.currency)}`}
                        </Button>
                        <p className="mt-3 flex items-center justify-center gap-1.5 text-center text-xs text-slate-400">
                            <ShieldCheck className="h-3.5 w-3.5" />
                            {app.demo_mode ? 'Demo mode: completes instantly, no real money is charged.' : 'Secure payment via Mobile Money or card.'}
                        </p>
                    </form>
                </div>
            </div>
        </PublicLayout>
    );
}
