import { DataTable } from '@/Components/app/DataTable';
import { Accent, Avatar, EmptyState, Panel } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Field, Input, Select } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Check, Copy, Megaphone, MousePointerClick, Pause, Play, Plus, Trash2, X } from 'lucide-react';
import { FormEvent, useState } from 'react';

type P = {
    id: number; name: string; email: string | null; code: string; event: string | null; link: string | null;
    commission_percent: number; clicks: number; orders: number; revenue_minor: number; earned_minor: number;
    conversion: number | null; is_active: boolean;
};

function CopyLink({ p, sample }: { p: P; sample: string | null }) {
    const [copied, setCopied] = useState(false);
    const url = p.link ?? (sample ? `${sample}?ref=${p.code}` : null);
    if (!url) return <span className="text-xs text-slate-400">Add ?ref={p.code} to any event link</span>;

    return (
        <button
            type="button"
            onClick={() => { navigator.clipboard?.writeText(url); setCopied(true); setTimeout(() => setCopied(false), 1500); }}
            className="inline-flex h-8 max-w-full items-center gap-1.5 rounded-full border border-tint/[0.1] bg-card px-3 text-xs font-semibold text-ink hover:border-brand-400"
            title={url}
        >
            {copied ? <Check className="h-3.5 w-3.5 text-emerald-600" /> : <Copy className="h-3.5 w-3.5" />}
            <span className="truncate">{copied ? 'Copied!' : `?ref=${p.code}`}</span>
        </button>
    );
}

function Actions({ p }: { p: P }) {
    return (
        <div className="flex justify-end gap-1.5">
            <Button size="sm" variant="outline" aria-label={p.is_active ? 'Pause' : 'Activate'} onClick={() => router.post(`/dashboard/promoters/${p.id}/toggle`, {}, { preserveScroll: true })}>
                {p.is_active ? <Pause className="h-4 w-4" /> : <Play className="h-4 w-4" />}
            </Button>
            <Button size="sm" variant="ghost" aria-label="Remove" onClick={() => window.confirm(`Remove ${p.name}?`) && router.delete(`/dashboard/promoters/${p.id}`, { preserveScroll: true })}>
                <Trash2 className="h-4 w-4 text-danger" />
            </Button>
        </div>
    );
}

export default function Promoters({ promoters, events, sample_url, currency }: PageProps<{ promoters: P[]; events: { id: number; title: string }[]; sample_url: string | null; currency: string }>) {
    const [open, setOpen] = useState(promoters.length === 0);
    const form = useForm({ name: '', email: '', code: '', commission_percent: '10', event_id: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/dashboard/promoters', { preserveScroll: true, onSuccess: () => { form.reset(); setOpen(false); } });
    }

    const revenue = promoters.reduce((n, p) => n + p.revenue_minor, 0);
    const owed = promoters.reduce((n, p) => n + p.earned_minor, 0);

    return (
        <AppLayout
            title={<>Promo<Accent>ters</Accent></>}
            subtitle={`${money(revenue, currency)} in referred sales · ${money(owed, currency)} commission earned`}
            actions={<Button size="sm" onClick={() => setOpen(!open)}>{open ? <X className="h-4 w-4" /> : <Plus className="h-4 w-4" />} {open ? 'Close' : 'Add promoter'}</Button>}
        >
            <Head title="Promoters" />

            <AnimatePresence initial={false}>
                {open && (
                    <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} className="overflow-hidden">
                        <Panel title="New promoter" subtitle="Each promoter gets a tracking link. Sales through it are credited to them." className="mb-5">
                            <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                                <Field label="Name" error={form.errors.name}>
                                    <Input
                                        value={form.data.name}
                                        onChange={(e) => form.setData('name', e.target.value)}
                                        onBlur={() => !form.data.code && form.setData('code', form.data.name.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 12))}
                                        placeholder="DJ Shiru"
                                        required
                                    />
                                </Field>
                                <Field label="Email (optional)" error={form.errors.email}>
                                    <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                                </Field>
                                <Field label="Referral code" error={form.errors.code}>
                                    <Input value={form.data.code} onChange={(e) => form.setData('code', e.target.value.toUpperCase().replace(/\s/g, ''))} placeholder="SHIRU" className="font-mono uppercase" required />
                                </Field>
                                <Field label="Commission %" error={form.errors.commission_percent}>
                                    <Input type="number" min={0} max={100} step="0.5" value={form.data.commission_percent} onChange={(e) => form.setData('commission_percent', e.target.value)} required />
                                </Field>
                                <Field label="Promotes" error={form.errors.event_id}>
                                    <Select value={form.data.event_id} onChange={(e) => form.setData('event_id', e.target.value)}>
                                        <option value="">All my events</option>
                                        {events.map((ev) => <option key={ev.id} value={ev.id}>{ev.title}</option>)}
                                    </Select>
                                </Field>
                                <div className="flex items-end">
                                    <Button type="submit" className="w-full" disabled={form.processing}><Megaphone className="h-4 w-4" /> Add promoter</Button>
                                </div>
                            </form>
                        </Panel>
                    </motion.div>
                )}
            </AnimatePresence>

            <DataTable
                rows={promoters}
                rowKey={(p) => p.id}
                empty={<EmptyState icon={Megaphone} title="No promoters yet" body="Give influencers, street teams and partners their own trackable links." />}
                columns={[
                    {
                        header: 'Promoter',
                        cell: (p) => (
                            <div className="flex items-center gap-3">
                                <Avatar name={p.name} className="h-9 w-9" />
                                <div className="min-w-0">
                                    <p className="truncate font-semibold text-ink">{p.name}</p>
                                    <p className="truncate text-xs text-slate-400">{p.event ?? 'All events'} · {p.commission_percent}%</p>
                                </div>
                            </div>
                        ),
                    },
                    { header: 'Link', cell: (p) => <CopyLink p={p} sample={sample_url} /> },
                    { header: 'Clicks', align: 'right', cell: (p) => p.clicks.toLocaleString() },
                    { header: 'Orders', align: 'right', cell: (p) => <span>{p.orders}{p.conversion !== null && <span className="ml-1 text-xs text-slate-400">({p.conversion}%)</span>}</span> },
                    { header: 'Sales', align: 'right', cell: (p) => <span className="whitespace-nowrap font-semibold text-ink">{money(p.revenue_minor, currency)}</span> },
                    { header: 'Earned', align: 'right', cell: (p) => <span className="whitespace-nowrap font-semibold text-emerald-700">{money(p.earned_minor, currency)}</span> },
                    { header: 'Status', cell: (p) => <Badge tone={p.is_active ? 'success' : 'neutral'}>{p.is_active ? 'Active' : 'Paused'}</Badge> },
                    { header: '', align: 'right', cell: (p) => <Actions p={p} /> },
                ]}
                mobile={(p) => (
                    <div>
                        <div className="flex items-center gap-3">
                            <Avatar name={p.name} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-bold text-ink">{p.name}</p>
                                <p className="truncate text-xs text-slate-400">{p.event ?? 'All events'} · {p.commission_percent}% commission</p>
                            </div>
                            <Badge tone={p.is_active ? 'success' : 'neutral'}>{p.is_active ? 'Active' : 'Paused'}</Badge>
                        </div>
                        <div className="mt-3 grid grid-cols-3 gap-2 rounded-2xl bg-tint/[0.04] p-3 text-center">
                            <div><p className="flex items-center justify-center gap-1 text-sm font-bold text-ink"><MousePointerClick className="h-3.5 w-3.5" />{p.clicks}</p><p className="text-[11px] text-slate-400">Clicks</p></div>
                            <div><p className="text-sm font-bold text-ink">{p.orders}</p><p className="text-[11px] text-slate-400">Orders</p></div>
                            <div><p className="truncate text-sm font-bold text-emerald-700">{money(p.earned_minor, currency)}</p><p className="text-[11px] text-slate-400">Earned</p></div>
                        </div>
                        <div className="mt-3 flex items-center justify-between gap-2">
                            <CopyLink p={p} sample={sample_url} />
                            <Actions p={p} />
                        </div>
                    </div>
                )}
            />
        </AppLayout>
    );
}
