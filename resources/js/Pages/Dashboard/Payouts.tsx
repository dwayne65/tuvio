import { Accent, EmptyState, Panel } from '@/Components/app/Kit';
import { Reveal } from '@/Components/motion/Reveal';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Field, Input, Select } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { AlertCircle, ArrowUpRight, Building2, Loader2, Plus, Smartphone, Star, Trash2, Wallet } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Account = { id: number; type: string; provider: string | null; label: string | null; is_default: boolean; account_name: string; masked_number: string; verification_status: string };
type Payout = { reference: string; amount_minor: number; currency: string; status: string; destination: string | null; reason: string | null; requested_at: string | null; processed_at: string | null };

const tone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    paid: 'success', requested: 'warning', pending: 'warning', processing: 'warning', failed: 'danger', cancelled: 'neutral',
};

const statusLabel: Record<string, string> = {
    requested: 'In review', pending: 'In review', processing: 'Sending', paid: 'Paid', failed: 'Failed', cancelled: 'Rejected',
};

export default function Payouts({ available_minor, currency, has_open_payout, accounts, payouts }: PageProps<{
    available_minor: number; currency: string; has_open_payout: boolean; accounts: Account[]; payouts: Payout[];
}>) {
    const { app } = usePage<PageProps>().props;
    const [adding, setAdding] = useState(false);
    const acc = useForm({ type: 'mobile_money', provider: 'MTN', label: '', account_name: '', account_number: '', bank_code: '' });
    const isBank = acc.data.type === 'bank';
    const canRequest = available_minor > 0 && accounts.length > 0;

    function addAccount(e: FormEvent) {
        e.preventDefault();
        acc.post('/dashboard/payouts/accounts', { preserveScroll: true, onSuccess: () => { acc.reset(); setAdding(false); } });
    }

    return (
        <AppLayout title={<>Pay<Accent>outs</Accent></>} subtitle="Move your ticket earnings to Mobile Money or your bank.">
            <Head title="Payouts" />

            <div className="grid gap-5 lg:grid-cols-[1.3fr_1fr]">
                {/* Balance */}
                <Reveal>
                    <div className="relative h-full overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#0b1030] via-[#24115e] to-brand-700 p-6 text-white shadow-premium sm:p-7">
                        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-brand-400/30 blur-3xl" />
                        <div className="relative">
                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-white/60">Available for payout</p>
                            <p className="mt-2 text-[2.4rem] font-extrabold leading-none tracking-[-0.04em] sm:text-5xl">{money(available_minor, currency)}</p>
                            <p className="mt-3 max-w-md text-sm text-white/70">
                                Money from each sale is available to cash out as soon as the payment is confirmed.
                            </p>
                            <Button
                                className="mt-6 bg-white text-[#0b0d12] hover:bg-white/90"
                                variant="secondary"
                                disabled={!canRequest}
                                onClick={() => router.post('/dashboard/payouts/request', {}, { preserveScroll: true })}
                            >
                                Request payout <ArrowUpRight className="h-4 w-4" />
                            </Button>
                            {accounts.length === 0 && <p className="mt-3 text-sm text-amber-300">Add a payout account first.</p>}
                            {has_open_payout && (
                                <p className="mt-3 flex items-center gap-2 text-sm text-white/70">
                                    <Loader2 className="h-4 w-4 animate-spin" /> A payout is on its way. Mobile Money usually lands within minutes of approval.
                                </p>
                            )}
                        </div>
                    </div>
                </Reveal>

                {/* Accounts */}
                <Reveal delay={0.05}>
                    <Panel
                        title="Payout accounts"
                        className="h-full"
                        action={!adding && <Button variant="outline" size="sm" onClick={() => setAdding(true)}><Plus className="h-4 w-4" /> Add</Button>}
                        bodyClassName="pt-4"
                    >
                        <div className="space-y-2.5">
                            {accounts.length === 0 && !adding && <p className="text-sm text-slate-500">No accounts yet.</p>}
                            {accounts.map((a) => (
                                <div key={a.id} className="flex items-center gap-3 rounded-2xl border border-tint/[0.07] bg-tint/[0.02] p-3">
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-500/10 text-brand-300">
                                        {a.type === 'bank' ? <Building2 className="h-[18px] w-[18px]" /> : <Smartphone className="h-[18px] w-[18px]" />}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-ink">{a.label || a.account_name}</p>
                                        <p className="truncate text-xs text-slate-400">
                                            {a.provider ?? (a.type === 'bank' ? 'Bank' : 'Mobile Money')} · <span className="font-mono">{a.masked_number}</span>
                                        </p>
                                    </div>
                                    {a.is_default ? (
                                        <Badge tone="brand">Default</Badge>
                                    ) : (
                                        <Button variant="ghost" size="sm" title="Make default" onClick={() => router.post(`/dashboard/payouts/accounts/${a.id}/default`, {}, { preserveScroll: true })}>
                                            <Star className="h-4 w-4" />
                                        </Button>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        title="Remove account"
                                        onClick={() => window.confirm(`Remove ${a.label || a.account_name} (${a.masked_number})?`) && router.delete(`/dashboard/payouts/accounts/${a.id}`, { preserveScroll: true })}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>

                        <AnimatePresence initial={false}>
                            {adding && (
                                <motion.form
                                    onSubmit={addAccount}
                                    initial={{ opacity: 0, height: 0 }}
                                    animate={{ opacity: 1, height: 'auto' }}
                                    exit={{ opacity: 0, height: 0 }}
                                    className="overflow-hidden"
                                >
                                    <div className="mt-3 space-y-3 rounded-2xl border border-brand-500/25 bg-brand-500/5 p-4">
                                        <div className="grid grid-cols-2 gap-3">
                                            <Field label="Type">
                                                <Select value={acc.data.type} onChange={(e) => acc.setData({ ...acc.data, type: e.target.value, provider: e.target.value === 'bank' ? '' : 'MTN' })}>
                                                    <option value="mobile_money">Mobile Money</option>
                                                    <option value="bank">Bank</option>
                                                </Select>
                                            </Field>
                                            {isBank ? (
                                                <Field label="Bank" error={acc.errors.provider}><Input value={acc.data.provider} onChange={(e) => acc.setData('provider', e.target.value)} placeholder="Stanbic" required /></Field>
                                            ) : (
                                                <Field label="Network" error={acc.errors.provider}>
                                                    <Select value={acc.data.provider} onChange={(e) => acc.setData('provider', e.target.value)}>
                                                        <option value="MTN">MTN MoMo</option>
                                                        <option value="Airtel">Airtel Money</option>
                                                    </Select>
                                                </Field>
                                            )}
                                        </div>
                                        <Field label={isBank ? 'Account name' : 'Name registered on the number'} error={acc.errors.account_name}><Input value={acc.data.account_name} onChange={(e) => acc.setData('account_name', e.target.value)} autoComplete="name" required /></Field>
                                        <Field label={isBank ? 'Account number' : 'Mobile Money number'} error={acc.errors.account_number}>
                                            <Input inputMode={isBank ? 'numeric' : 'tel'} value={acc.data.account_number} onChange={(e) => acc.setData('account_number', e.target.value)} placeholder={isBank ? '9030012345678' : '0772 123456'} required />
                                        </Field>
                                        {isBank && (
                                            <Field label="Branch or SWIFT code (optional)" error={acc.errors.bank_code}><Input value={acc.data.bank_code} onChange={(e) => acc.setData('bank_code', e.target.value)} /></Field>
                                        )}
                                        {!isBank && <p className="text-xs text-slate-500">Payouts are sent straight to this number. Double-check it: money sent to a wrong number can't be recalled.</p>}
                                        <div className="flex gap-2">
                                            <Button type="submit" size="sm" disabled={acc.processing}>Save account</Button>
                                            <Button type="button" size="sm" variant="ghost" onClick={() => setAdding(false)}>Cancel</Button>
                                        </div>
                                    </div>
                                </motion.form>
                            )}
                        </AnimatePresence>
                    </Panel>
                </Reveal>
            </div>

            <Reveal className="mt-5">
                <Panel title="Payout history" bodyClassName="pt-3">
                    {payouts.length === 0 ? (
                        <EmptyState icon={Wallet} title="No payouts yet" body="Your payout requests will be listed here." />
                    ) : (
                        <ul className="-mx-2 divide-y divide-tint/[0.06]">
                            {payouts.map((p) => (
                                <li key={p.reference} className="flex items-center gap-3 px-2 py-3.5">
                                    <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-500/10 text-brand-300">
                                        <ArrowUpRight className="h-[18px] w-[18px]" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-mono text-xs text-slate-500">{p.reference}</p>
                                        <p className="truncate text-sm text-slate-500">
                                            {p.requested_at ? formatDate(p.requested_at) : '—'}
                                            {p.destination && <> · to {p.destination}</>}
                                        </p>
                                        {p.reason && (
                                            <p className="mt-1 flex items-start gap-1.5 text-xs text-rose-400">
                                                <AlertCircle className="mt-px h-3.5 w-3.5 shrink-0" /> {p.reason} The money is back in your balance.
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex shrink-0 flex-col items-end gap-1">
                                        <span className="text-sm font-bold text-ink">{money(p.amount_minor, p.currency)}</span>
                                        <Badge tone={tone[p.status] ?? 'neutral'}>{statusLabel[p.status] ?? p.status}</Badge>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </Reveal>
        </AppLayout>
    );
}
