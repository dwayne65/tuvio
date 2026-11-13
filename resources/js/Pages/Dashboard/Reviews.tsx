import { Accent, Avatar, EmptyState, Pagination } from '@/Components/app/Kit';
import { Stars } from '@/Components/app/Stars';
import { RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { StatCard } from '@/Components/StatCard';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDate } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { Eye, EyeOff, MessageSquareText, Star, StarHalf } from 'lucide-react';

type R = { id: number; name: string; rating: number; body: string | null; event: string; event_slug: string; is_visible: boolean; at: string };
type Links = { url: string | null; label: string; active: boolean }[];

export default function Reviews({ reviews, summary }: PageProps<{
    reviews: { data: R[]; links: Links };
    summary: { count: number; average: number | null; hidden: number };
}>) {
    return (
        <AppLayout title={<>Re<Accent>views</Accent></>} subtitle="What verified buyers say about your events.">
            <Head title="Reviews" />

            <RevealGroup className="grid grid-cols-3 gap-3 sm:gap-5">
                <RevealItem><StatCard icon={Star} label="Average" value={summary.average === null ? '—' : `${summary.average} / 5`} tone="amber" /></RevealItem>
                <RevealItem><StatCard icon={MessageSquareText} label="Reviews" value={summary.count.toLocaleString()} /></RevealItem>
                <RevealItem><StatCard icon={StarHalf} label="Hidden" value={summary.hidden.toLocaleString()} tone="blue" /></RevealItem>
            </RevealGroup>

            <div className="mt-5 space-y-3">
                {reviews.data.length === 0 ? (
                    <EmptyState icon={Star} title="No reviews yet" body="Buyers can review an event once it starts or their ticket is scanned." />
                ) : (
                    reviews.data.map((r) => (
                        <article key={r.id} className={cn('rounded-[1.25rem] border border-tint/[0.07] bg-card p-4 shadow-[var(--shadow-card)] sm:p-5', !r.is_visible && 'opacity-60')}>
                            <div className="flex items-start gap-3">
                                <Avatar name={r.name} />
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <p className="font-bold text-ink">{r.name}</p>
                                        <Stars value={r.rating} />
                                        {!r.is_visible && <Badge tone="neutral">Hidden</Badge>}
                                    </div>
                                    <p className="text-xs text-slate-400">
                                        <Link href={`/events/${r.event_slug}`} className="hover:text-ink">{r.event}</Link> · {formatDate(r.at)}
                                    </p>
                                    {r.body && <p className="mt-2 whitespace-pre-line text-sm text-slate-600">{r.body}</p>}
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    aria-label={r.is_visible ? 'Hide review' : 'Show review'}
                                    onClick={() => router.post(`/dashboard/reviews/${r.id}/toggle`, {}, { preserveScroll: true })}
                                >
                                    {r.is_visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    <span className="hidden sm:inline">{r.is_visible ? 'Hide' : 'Show'}</span>
                                </Button>
                            </div>
                        </article>
                    ))
                )}
            </div>
            <Pagination links={reviews.links} />
        </AppLayout>
    );
}
