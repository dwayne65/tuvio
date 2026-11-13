import { Accent, EmptyState, Panel } from '@/Components/app/Kit';
import { StatCard } from '@/Components/StatCard';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { Button } from '@/Components/ui/Button';
import { Field, Input, Select, Textarea } from '@/Components/ui/Input';
import AdminLayout from '@/Layouts/AdminLayout';
import { cn, formatDate, formatDateTime, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowDownToLine, CreditCard, HandCoins, Landmark, Percent, Receipt, ShieldCheck, Wallet } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Summary = {
    commission: number;
    service_fees: number;
    subscriptions: number;
    processor_fees: number;
    refunds: number;
    earned: number;
    withdrawn: number;
    available: number;
    tax_held: number;
};

type Month = { month: string; commission: number; service_fees: number; subscriptions: number };

type Withdrawal = {
    reference: string;
    amount_minor: number;
    currency: string;
    method: string;
    destination: string;
    provider_ref: string | null;
    note: string | null;
    by: string | null;
    paid_at: string;
};

type PlanPayment = { number: string; organization: string | null; plan: string; amount_minor: number; currency: string; paid_at: string | null };

export default function AdminEarnings({ currency, summary, monthly, taxLabel, withdrawals, planPayments }: PageProps<{
    currency: string;
    summary: Summary;
    monthly: Month[];
    taxLabel: string;
    withdrawals: Withdrawal[];
    planPayments: PlanPayment[];
}>) {
    const [withdrawing, setWithdrawing] = useState(false);
    const peak = Math.max(1, ...monthly.map((m) => m.commission + m.service_fees + m.subscriptions));

    return (
        <AdminLayout
            title={<>Plat<Accent>form earnings</Accent></>}
            subtitle="Commission, booking fees and plan payments the platform has earned, and what you can withdraw."
            actions={
                <Button onClick={() => setWithdrawing(true)} disabled={summary.available <= 0}>
                    <ArrowDownToLine className="h-4 w-4" /> Withdraw
                </Button>
            }
        >
            <Head title="Admin · Earnings" />

            <Reveal>
                <div className="relative overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#0b1030] via-[#24115e] to-brand-700 p-6 text-white shadow-premium sm:p-7">
                    <div className="dot-grid pointer-events-none absolute inset-0 opacity-30" />
                    <div className="relative grid gap-6 sm:grid-cols-3">
                        <div className="sm:col-span-2">
                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-white/60">Available to withdraw</p>
                            <p className="mt-2 text-[2.4rem] font-extrabold leading-none tracking-[-0.04em] sm:text-5xl">{money(summary.available, currency)}</p>
                            <p className="mt-3 text-sm text-white/70">
                                {money(summary.earned, currency)} earned in total · {money(summary.withdrawn, currency)} already withdrawn
                            </p>
                        </div>
                        <div className="rounded-2xl border border-white/15 bg-white/10 p-4 text-sm backdrop-blur">
                            <p className="flex items-center gap-2 font-semibold"><ShieldCheck className="h-4 w-4" /> Held for tax</p>
                            <p className="mt-1 text-2xl font-extrabold">{money(summary.tax_held, currency)}</p>
                            <p className="mt-1 text-xs text-white/70">{taxLabel} collected on platform fees. It belongs to the tax authority and is never part of what you can withdraw.</p>
                        </div>
                    </div>
                </div>
            </Reveal>

            <RevealGroup className="mt-5 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                <RevealItem><StatCard icon={Percent} label="Commission" value={money(summary.commission, currency)} hint="Taken from organizer sales" tone="brand" /></RevealItem>
                <RevealItem><StatCard icon={Receipt} label="Booking fees" value={money(summary.service_fees, currency)} hint="Paid by buyers at checkout" tone="blue" /></RevealItem>
                <RevealItem><StatCard icon={CreditCard} label="Plan payments" value={money(summary.subscriptions, currency)} hint="Enterprise subscriptions" tone="emerald" /></RevealItem>
                <RevealItem><StatCard icon={HandCoins} label="Processor charges" value={`− ${money(summary.processor_fees + summary.refunds, currency)}`} hint="Mobile Money, card and refunds" tone="amber" /></RevealItem>
            </RevealGroup>

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <Reveal className="lg:col-span-2">
                    <Panel title="Income by month" subtitle="Last 6 months" bodyClassName="pt-4">
                        <div className="flex h-56 items-end gap-3">
                            {monthly.map((m) => {
                                const total = m.commission + m.service_fees + m.subscriptions;
                                return (
                                    <div key={m.month} className="flex h-full flex-1 flex-col items-center justify-end gap-2" title={`${money(total, currency)}`}>
                                        <span className="text-[11px] font-semibold text-slate-500">{total > 0 ? money(total, currency).replace(/\.00$/, '') : ''}</span>
                                        <div className="flex w-full max-w-[3.5rem] flex-col-reverse overflow-hidden rounded-xl bg-tint/[0.04]" style={{ height: `${Math.max(4, (total / peak) * 100)}%` }}>
                                            <span className="bg-brand-500" style={{ height: `${total ? (m.commission / total) * 100 : 0}%` }} />
                                            <span className="bg-sky-400" style={{ height: `${total ? (m.service_fees / total) * 100 : 0}%` }} />
                                            <span className="bg-emerald-400" style={{ height: `${total ? (m.subscriptions / total) * 100 : 0}%` }} />
                                        </div>
                                        <span className="text-xs text-slate-400">{new Date(`${m.month}-01T00:00:00`).toLocaleDateString('en-US', { month: 'short' })}</span>
                                    </div>
                                );
                            })}
                        </div>
                        <div className="mt-4 flex flex-wrap gap-4 text-xs text-slate-500">
                            <Legend className="bg-brand-500" label="Commission" />
                            <Legend className="bg-sky-400" label="Booking fees" />
                            <Legend className="bg-emerald-400" label="Plan payments" />
                        </div>
                    </Panel>
                </Reveal>

                <Reveal delay={0.05}>
                    <Panel title="Recent plan payments" className="h-full" bodyClassName="pt-2">
                        {planPayments.length === 0 ? (
                            <p className="py-8 text-center text-sm text-slate-400">No plan payments yet.</p>
                        ) : (
                            <ul className="divide-y divide-tint/[0.06]">
                                {planPayments.map((p) => (
                                    <li key={p.number} className="flex items-center justify-between gap-3 py-3 text-sm">
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-ink">{p.organization ?? 'Organization'}</p>
                                            <p className="text-xs text-slate-400">{p.plan}{p.paid_at ? ` · ${formatDate(p.paid_at)}` : ''}</p>
                                        </div>
                                        <span className="font-semibold text-ink">{money(p.amount_minor, p.currency)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Panel>
                </Reveal>
            </div>

            <Reveal className="mt-5">
                <Panel title="Withdrawals" subtitle="Every withdrawal is posted to the ledger and the audit trail." bodyClassName="pt-2">
                    {withdrawals.length === 0 ? (
                        <EmptyState icon={Wallet} title="No withdrawals yet" body="When you take earnings out, they are listed here." />
                    ) : (
                        <ul className="divide-y divide-tint/[0.06]">
                            {withdrawals.map((w) => (
                                <li key={w.reference} className="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                                    <div className="flex min-w-0 items-center gap-3">
                                        <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-500/10 text-brand-500">
                                            {w.method === 'bank' ? <Landmark className="h-4 w-4" /> : <Wallet className="h-4 w-4" />}
                                        </span>
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold text-ink">{w.destination}</p>
                                            <p className="text-xs text-slate-400">
                                                <span className="font-mono">{w.reference}</span> · {formatDateTime(w.paid_at)}{w.by ? ` · ${w.by}` : ''}{w.provider_ref ? ` · Ref ${w.provider_ref}` : ''}
                                            </p>
                                        </div>
                                    </div>
                                    <span className="font-bold text-ink">− {money(w.amount_minor, w.currency)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </Reveal>

            {withdrawing && <WithdrawDialog available={summary.available} currency={currency} onClose={() => setWithdrawing(false)} />}
        </AdminLayout>
    );
}

function Legend({ className, label }: { className: string; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5">
            <span className={cn('h-2.5 w-2.5 rounded-full', className)} /> {label}
        </span>
    );
}

function WithdrawDialog({ available, currency, onClose }: { available: number; currency: string; onClose: () => void }) {
    const form = useForm({ amount: String(Math.floor(available / 100)), method: 'mobile_money', destination: '', provider_ref: '', note: '', password: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/admin/earnings/withdraw', { preserveScroll: true, onSuccess: onClose, onFinish: () => form.reset('password') });
    }

    return (
        <div className="fixed inset-0 z-[70] grid place-items-center bg-black/60 p-4 backdrop-blur-sm" onClick={onClose}>
            <form onSubmit={submit} onClick={(e) => e.stopPropagation()} className="w-full max-w-md rounded-[1.75rem] border border-tint/[0.08] bg-card p-6 shadow-premium">
                <h2 className="text-xl font-extrabold tracking-[-0.03em] text-ink">Withdraw earnings</h2>
                <p className="mt-1 text-sm text-slate-500">Up to {money(available, currency)} is available. Send the money from your payment provider account, then record it here.</p>

                <div className="mt-5 space-y-4">
                    <Field label={`Amount (${currency})`} error={form.errors.amount}>
                        <Input inputMode="numeric" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value.replace(/[^\d.]/g, ''))} required />
                    </Field>
                    <Field label="Send to" error={form.errors.method}>
                        <Select value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                            <option value="mobile_money">Mobile Money</option>
                            <option value="bank">Bank account</option>
                        </Select>
                    </Field>
                    <Field label={form.data.method === 'bank' ? 'Bank and account number' : 'Mobile Money number'} error={form.errors.destination}>
                        <Input value={form.data.destination} onChange={(e) => form.setData('destination', e.target.value)} placeholder={form.data.method === 'bank' ? 'Stanbic · 9030012345678' : '0770 123456'} required />
                    </Field>
                    <Field label="Transaction reference (optional)" error={form.errors.provider_ref}>
                        <Input value={form.data.provider_ref} onChange={(e) => form.setData('provider_ref', e.target.value)} placeholder="From Paytota or your bank" />
                    </Field>
                    <Field label="Note (optional)" error={form.errors.note}>
                        <Textarea rows={2} value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                    </Field>
                    <Field label="Confirm with your password" error={form.errors.password}>
                        <Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="current-password" required />
                    </Field>
                </div>

                <div className="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={form.processing}><ArrowDownToLine className="h-4 w-4" /> Record withdrawal</Button>
                </div>
            </form>
        </div>
    );
}
