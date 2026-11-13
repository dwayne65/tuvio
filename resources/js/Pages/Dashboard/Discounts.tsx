import { DataTable } from '@/Components/app/DataTable';
import { Accent, EmptyState, FilterChips, Panel } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Field, Input, Select } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Pause, Play, Plus, Tag, Trash2, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

type Code = {
    id: number; code: string; type: 'percent' | 'fixed'; value: number; currency: string; event: string | null;
    redeemed: number; max: number | null; discounted_minor: number; ends_at: string | null; is_active: boolean; redeemable: boolean;
};

function label(c: Code) {
    return c.type === 'percent' ? `${c.value}% off` : `${money(c.value, c.currency)} off`;
}

function status(c: Code) {
    if (!c.is_active) return <Badge tone="neutral">Paused</Badge>;
    if (!c.redeemable) return <Badge tone="warning">Ended</Badge>;
    return <Badge tone="success">Active</Badge>;
}

function Usage({ c }: { c: Code }) {
    const pct = c.max ? Math.min(100, (c.redeemed / c.max) * 100) : 0;
    return (
        <div className="min-w-[7rem]">
            <p className="text-sm font-semibold text-ink">{c.redeemed}{c.max ? ` / ${c.max}` : ''} <span className="font-normal text-slate-400">used</span></p>
            {c.max && (
                <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-tint/[0.08]">
                    <div className="h-full rounded-full bg-brand-500" style={{ width: `${pct}%` }} />
                </div>
            )}
        </div>
    );
}

function Actions({ c }: { c: Code }) {
    return (
        <div className="flex justify-end gap-1.5">
            <Button size="sm" variant="outline" onClick={() => router.post(`/dashboard/discounts/${c.id}/toggle`, {}, { preserveScroll: true })} aria-label={c.is_active ? 'Pause' : 'Activate'}>
                {c.is_active ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />}
            </Button>
            <Button
                size="sm"
                variant="ghost"
                aria-label="Delete"
                onClick={() => window.confirm(`Delete ${c.code}?`) && router.delete(`/dashboard/discounts/${c.id}`, { preserveScroll: true })}
            >
                <Trash2 className="h-4 w-4 text-danger" />
            </Button>
        </div>
    );
}

export default function Discounts({ codes, events, currency }: PageProps<{ codes: Code[]; events: { id: number; title: string }[]; currency: string }>) {
    const [open, setOpen] = useState(codes.length === 0);
    const form = useForm({ code: '', type: 'percent' as 'percent' | 'fixed', value: '', event_id: '', max_redemptions: '', min_order: '', ends_at: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/dashboard/discounts', { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } });
    }

    const saved = codes.reduce((n, c) => n + c.discounted_minor, 0);
    const used = codes.reduce((n, c) => n + c.redeemed, 0);

    return (
        <AppLayout
            title={<>Dis<Accent>counts</Accent></>}
            subtitle={`${codes.filter((c) => c.is_active && c.redeemable).length} active codes · ${used} redemptions · ${money(saved, currency)} given back to buyers`}
            actions={<Button size="sm" onClick={() => setOpen(!open)}>{open ? <X className="h-4 w-4" /> : <Plus className="h-4 w-4" />} {open ? 'Close' : 'New code'}</Button>}
        >
            <Head title="Discounts" />

            <AnimatePresence initial={false}>
                {open && (
                    <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                        <Panel title="New promo code" subtitle="Buyers enter it on the checkout page." className="mb-5">
                            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                                <Field label="Code" error={form.errors.code}>
                                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase().replace(/\s/g, ''))} placeholder="EARLYBIRD" className="font-mono uppercase" required />
                                </Field>
                                <div className="space-y-1.5">
                                    <span className="text-sm font-medium text-ink">Discount type</span>
                                    <FilterChips
                                        options={[{ value: 'percent', label: 'Percentage' }, { value: 'fixed', label: `Fixed ${currency}` }]}
                                        value={form.data.type}
                                        onChange={(v) => form.setData('type', v)}
                                    />
                                </div>
                                <Field label={form.data.type === 'percent' ? 'Percent off' : `Amount off (${currency})`} error={form.errors.value}>
                                    <Input type="number" inputMode="decimal" min={1} max={form.data.type === 'percent' ? 100 : undefined} value={form.data.value} onChange={(e) => form.setData('value', e.target.value)} placeholder={form.data.type === 'percent' ? '10' : '5000'} required />
                                </Field>
                                <Field label="Applies to" error={form.errors.event_id}>
                                    <Select value={form.data.event_id} onChange={(e) => form.setData('event_id', e.target.value)}>
                                        <option value="">All my events</option>
                                        {events.map((ev) => <option key={ev.id} value={ev.id}>{ev.title}</option>)}
                                    </Select>
                                </Field>
                                <Field label="Usage limit (optional)" error={form.errors.max_redemptions}>
                                    <Input type="number" min={1} value={form.data.max_redemptions} onChange={(e) => form.setData('max_redemptions', e.target.value)} placeholder="Unlimited" />
                                </Field>
                                <Field label={`Minimum order (${currency}, optional)`} error={form.errors.min_order}>
                                    <Input type="number" min={0} value={form.data.min_order} onChange={(e) => form.setData('min_order', e.target.value)} placeholder="None" />
                                </Field>
                                <Field label="Expires (optional)" error={form.errors.ends_at}>
                                    <Input type="datetime-local" value={form.data.ends_at} onChange={(e) => form.setData('ends_at', e.target.value)} />
                                </Field>
                                <div className="flex items-end">
                                    <Button type="submit" className="w-full" disabled={form.processing}><Tag className="h-4 w-4" /> Create code</Button>
                                </div>
                            </form>
                        </Panel>
                    </motion.div>
                )}
            </AnimatePresence>

            <DataTable
                rows={codes}
                rowKey={(c) => c.id}
                empty={<EmptyState icon={Tag} title="No promo codes yet" body="Create a code to reward early birds, groups or loyal fans." />}
                columns={[
                    { header: 'Code', cell: (c) => <div><p className="font-mono font-bold text-ink">{c.code}</p><p className="text-xs text-slate-400">{c.event ?? 'All events'}</p></div> },
                    { header: 'Discount', cell: (c) => <span className="font-semibold text-ink">{label(c)}</span> },
                    { header: 'Usage', cell: (c) => <Usage c={c} /> },
                    { header: 'Given', cell: (c) => <span className="whitespace-nowrap text-slate-600">{money(c.discounted_minor, c.currency)}</span> },
                    { header: 'Expires', cell: (c) => <span className="whitespace-nowrap text-slate-500">{c.ends_at ? formatDate(c.ends_at) : 'Never'}</span> },
                    { header: 'Status', cell: status },
                    { header: '', align: 'right', cell: (c) => <Actions c={c} /> },
                ]}
                mobile={(c) => (
                    <div>
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="font-mono text-lg font-bold text-ink">{c.code}</p>
                                <p className="text-sm font-semibold text-brand-300">{label(c)}</p>
                                <p className="text-xs text-slate-400">{c.event ?? 'All events'}{c.ends_at ? ` · until ${formatDate(c.ends_at)}` : ''}</p>
                            </div>
                            {status(c)}
                        </div>
                        <div className="mt-3 flex items-end justify-between gap-3 border-t border-tint/[0.06] pt-3">
                            <Usage c={c} />
                            <Actions c={c} />
                        </div>
                    </div>
                )}
            />
        </AppLayout>
    );
}
