import { Avatar } from '@/Components/app/Kit';
import { Stars } from '@/Components/app/Stars';
import { Button } from '@/Components/ui/Button';
import { Field, Input, Textarea } from '@/Components/ui/Input';
import { formatDate } from '@/lib/utils';
import { useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { BadgeCheck, PenLine, Star } from 'lucide-react';
import { FormEvent, useState } from 'react';

export type ReviewSummary = {
    average: number | null;
    count: number;
    breakdown: Record<string, number>;
    items: { id: number; name: string; rating: number; body: string | null; at: string }[];
};

export function EventReviews({ slug, reviews }: { slug: string; reviews: ReviewSummary }) {
    const [writing, setWriting] = useState(false);
    const form = useForm({ reference: '', email: '', rating: 0, body: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/events/${slug}/reviews`, {
            preserveScroll: true,
            onSuccess: () => { form.reset(); setWriting(false); },
        });
    }

    return (
        <section aria-labelledby="reviews-heading">
            <div className="flex items-end justify-between gap-3">
                <h2 id="reviews-heading" className="text-[1.35rem] font-extrabold tracking-[-0.03em] text-ink">
                    Reviews {reviews.count > 0 && <span className="text-base font-semibold text-slate-400">({reviews.count})</span>}
                </h2>
                <Button size="sm" variant="outline" onClick={() => setWriting(!writing)} aria-expanded={writing}>
                    <PenLine className="h-4 w-4" /> Write a review
                </Button>
            </div>

            {reviews.count > 0 && reviews.average !== null && (
                <div className="mt-4 flex items-center gap-5 rounded-[1.25rem] border border-tint/[0.07] bg-card/60 p-4">
                    <div className="text-center">
                        <p className="text-4xl font-extrabold tracking-tight text-ink">{reviews.average.toFixed(1)}</p>
                        <Stars value={reviews.average} className="mt-1 justify-center" />
                    </div>
                    <div className="flex-1 space-y-1">
                        {[5, 4, 3, 2, 1].map((r) => {
                            const n = reviews.breakdown[r] ?? 0;
                            return (
                                <div key={r} className="flex items-center gap-2 text-xs text-slate-500">
                                    <span className="flex w-6 items-center gap-0.5">{r}<Star className="h-3 w-3 fill-current" /></span>
                                    <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-tint/[0.08]">
                                        <div className="h-full rounded-full bg-amber-400" style={{ width: `${(n / reviews.count) * 100}%` }} />
                                    </div>
                                    <span className="w-5 text-right">{n}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            <AnimatePresence initial={false}>
                {writing && (
                    <motion.form
                        onSubmit={submit}
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        className="overflow-hidden"
                    >
                        <div className="mt-4 space-y-4 rounded-[1.25rem] border border-brand-500/25 bg-brand-500/[0.04] p-4 sm:p-5">
                            <p className="flex items-start gap-2 text-sm text-slate-500">
                                <BadgeCheck className="mt-0.5 h-4 w-4 shrink-0 text-brand-300" />
                                Only ticket buyers can review. Use the order reference from your ticket email (e.g. TF-AB12CD34).
                            </p>
                            <div className="flex flex-col items-center gap-1 py-1">
                                <Stars value={form.data.rating} onChange={(v) => form.setData('rating', v)} size="lg" />
                                {form.errors.rating && <p className="text-sm text-danger">Pick a rating from 1 to 5 stars.</p>}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Order reference" error={form.errors.reference}>
                                    <Input value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value.toUpperCase())} placeholder="TF-…" className="font-mono uppercase" required />
                                </Field>
                                <Field label="Email used at checkout" error={form.errors.email}>
                                    <Input type="email" inputMode="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                                </Field>
                            </div>
                            <Field label="Your review (optional)" error={form.errors.body}>
                                <Textarea value={form.data.body} onChange={(e) => form.setData('body', e.target.value)} rows={3} placeholder="What was the vibe like?" />
                            </Field>
                            <Button type="submit" className="w-full" disabled={form.processing || form.data.rating === 0}>
                                Post review
                            </Button>
                        </div>
                    </motion.form>
                )}
            </AnimatePresence>

            {reviews.items.length > 0 ? (
                <ul className="mt-4 space-y-3">
                    {reviews.items.map((r) => (
                        <li key={r.id} className="rounded-[1.25rem] border border-tint/[0.07] bg-card/60 p-4">
                            <div className="flex items-center gap-3">
                                <Avatar name={r.name} className="h-9 w-9" />
                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-1.5 truncate text-sm font-bold text-ink">
                                        {r.name} <BadgeCheck className="h-3.5 w-3.5 text-brand-300" aria-label="Verified buyer" />
                                    </p>
                                    <p className="text-xs text-slate-400">{formatDate(r.at)}</p>
                                </div>
                                <Stars value={r.rating} />
                            </div>
                            {r.body && <p className="mt-2.5 whitespace-pre-line text-sm text-slate-600">{r.body}</p>}
                        </li>
                    ))}
                </ul>
            ) : (
                !writing && <p className="mt-3 text-sm text-slate-400">No reviews yet. Been to this event? Be the first to share how it was.</p>
            )}
        </section>
    );
}
