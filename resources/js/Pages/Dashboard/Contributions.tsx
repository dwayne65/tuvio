import { Accent, EmptyState, Panel } from '@/Components/app/Kit';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ArrowUpRight, Gift, Plus, Users } from 'lucide-react';
import { FormEvent, useState } from 'react';

type C = { id: number; title: string; slug: string; status: string; goal_minor: number | null; raised_minor: number; currency: string; contributors_count: number; progress: number };

export default function Contributions({ campaigns }: PageProps<{ campaigns: C[] }>) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, reset, errors } = useForm({ title: '', description: '', goal_major: '', deadline_at: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/dashboard/campaigns', { preserveScroll: true, onSuccess: () => { reset(); setOpen(false); } });
    }

    return (
        <AppLayout
            title={<>Fund<Accent>raising</Accent></>}
            subtitle="Raise money from your community with a shareable campaign page."
            actions={!open && <Button onClick={() => setOpen(true)}><Plus className="h-4 w-4" /> New campaign</Button>}
        >
            <Head title="Contributions" />

            <AnimatePresence initial={false}>
                {open && (
                    <motion.div initial={{ opacity: 0, height: 0 }} animate={{ opacity: 1, height: 'auto' }} exit={{ opacity: 0, height: 0 }} className="overflow-hidden">
                        <Panel title="New campaign" subtitle="You can share the page as soon as it’s created." className="mb-5">
                            <form onSubmit={submit} className="space-y-4">
                                <Field label="Campaign title" error={errors.title}><Input value={data.title} onChange={(e) => setData('title', e.target.value)} required /></Field>
                                <Field label="Description" error={errors.description}>
                                    <textarea
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        rows={3}
                                        className="w-full rounded-2xl border border-tint/10 bg-tint/[0.04] px-4 py-3 text-[15px] text-ink placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15"
                                    />
                                </Field>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Goal amount (optional)" error={errors.goal_major}><Input type="number" inputMode="numeric" min="0" value={data.goal_major} onChange={(e) => setData('goal_major', e.target.value)} /></Field>
                                    <Field label="Deadline (optional)" error={errors.deadline_at}><Input type="date" value={data.deadline_at} onChange={(e) => setData('deadline_at', e.target.value)} /></Field>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={processing}>Create campaign</Button>
                                    <Button type="button" variant="ghost" onClick={() => setOpen(false)}>Cancel</Button>
                                </div>
                            </form>
                        </Panel>
                    </motion.div>
                )}
            </AnimatePresence>

            {campaigns.length === 0 ? (
                <EmptyState icon={Gift} title="No campaigns yet" body="Create one to start raising funds." action={!open && <Button onClick={() => setOpen(true)}><Plus className="h-4 w-4" /> New campaign</Button>} />
            ) : (
                <RevealGroup className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {campaigns.map((c) => (
                        <RevealItem key={c.id}>
                            <div className="flex h-full flex-col rounded-[1.5rem] border border-tint/[0.07] bg-card p-5 shadow-[var(--shadow-card)]">
                                <div className="flex items-center justify-between">
                                    <Badge tone={c.status === 'active' ? 'success' : 'neutral'}>{c.status}</Badge>
                                    <Link href={`/campaigns/${c.slug}`} className="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:underline">
                                        View <ArrowUpRight className="h-4 w-4" />
                                    </Link>
                                </div>
                                <h3 className="mt-3 line-clamp-2 text-lg font-bold text-ink">{c.title}</h3>
                                <div className="mt-auto pt-5">
                                    <div className="flex items-baseline justify-between gap-2">
                                        <span className="text-xl font-extrabold text-ink">{money(c.raised_minor, c.currency)}</span>
                                        {c.goal_minor ? <span className="text-sm text-slate-400">of {money(c.goal_minor, c.currency)}</span> : null}
                                    </div>
                                    <div className="mt-2 h-2.5 overflow-hidden rounded-full bg-tint/[0.06]">
                                        <div className="h-full rounded-full bg-gradient-to-r from-brand-400 to-brand-600" style={{ width: `${Math.min(100, c.progress)}%` }} />
                                    </div>
                                    <p className="mt-2 flex items-center gap-1.5 text-xs text-slate-400">
                                        <Users className="h-3.5 w-3.5" /> {c.contributors_count} contributors · {c.progress}%
                                    </p>
                                </div>
                            </div>
                        </RevealItem>
                    ))}
                </RevealGroup>
            )}
        </AppLayout>
    );
}
