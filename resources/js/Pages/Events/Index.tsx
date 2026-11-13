import { EventCard } from '@/Components/EventCard';
import PublicLayout from '@/Layouts/PublicLayout';
import { cn } from '@/lib/utils';
import { Category, EventSummary, PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { CalendarX, Search, X } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

type Props = {
    events: {
        data: EventSummary[];
        meta: { current_page: number; last_page: number; total: number };
        links: { url: string | null; label: string; active: boolean }[];
    };
    categories: Category[];
    filters: { q: string; category: string; type: string; sort: string };
};

const sorts = [
    { value: '', label: 'Featured' },
    { value: 'soon', label: 'Coming soon' },
    { value: 'newest', label: 'Newest' },
];

export default function EventsIndex({ events, categories, filters }: PageProps<Props>) {
    const [q, setQ] = useState(filters.q ?? '');

    function apply(extra: Record<string, string>) {
        router.get('/events', { ...filters, q, ...extra }, { preserveState: true, preserveScroll: true });
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        apply({});
    }

    return (
        <PublicLayout>
            <Head title="Discover Events" />

            <section className="container-page pt-3 md:pt-14">
                <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-300">Your next story starts here</p>
                <h1 className="mt-1.5 text-[2rem] font-extrabold tracking-[-0.045em] text-ink md:text-5xl">
                    Discover <span className="font-display font-normal italic tracking-normal text-gradient">events</span>
                </h1>
                <p className="mt-1 text-slate-500">{events.meta.total} upcoming {events.meta.total === 1 ? 'event' : 'events'} to explore</p>

                <form onSubmit={submit} className="mt-6 flex items-center gap-2.5 md:max-w-2xl">
                    <label className="relative flex-1">
                        <span className="sr-only">Search events</span>
                        <Search className="pointer-events-none absolute left-4 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400" />
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Events, artists, venues"
                            className="h-[3.25rem] w-full rounded-full border border-tint/10 bg-tint/[0.05] pl-11 pr-9 text-[15px] text-ink placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15"
                        />
                        {q && (
                            <button
                                type="button"
                                aria-label="Clear search"
                                onClick={() => {
                                    setQ('');
                                    router.get('/events', { ...filters, q: '' }, { preserveState: true });
                                }}
                                className="absolute right-3 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-full bg-white/10 text-slate-600"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </label>
                    <button type="submit" className="h-[3.25rem] shrink-0 rounded-full bg-brand-500 px-5 text-sm sm:px-6 font-bold text-white glow-blue active:scale-95">
                        Search
                    </button>
                </form>

                {/* Sort tabs */}
                <div className="mt-5 inline-flex rounded-full border border-tint/[0.08] bg-tint/[0.03] p-1">
                    {sorts.map((s) => (
                        <button
                            key={s.value}
                            onClick={() => apply({ sort: s.value })}
                            className={cn(
                                'rounded-full px-4 py-2 text-[13px] font-semibold transition-colors',
                                (filters.sort ?? '') === s.value ? 'bg-brand-500 text-white glow-blue' : 'text-slate-500 hover:text-ink',
                            )}
                        >
                            {s.label}
                        </button>
                    ))}
                </div>

                {/* Category chips */}
                <div className="scroller -mx-4 mt-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
                    <Chip active={!filters.category} onClick={() => apply({ category: '' })}>All</Chip>
                    {categories.map((c) => (
                        <Chip key={c.id} active={filters.category === c.slug} onClick={() => apply({ category: c.slug })}>
                            <span className="h-2 w-2 rounded-full" style={{ backgroundColor: c.color ?? '#6418f5' }} />
                            {c.name}
                        </Chip>
                    ))}
                </div>
            </section>

            <section className="container-page py-6 md:py-10">
                {events.data.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-[2rem] border border-dashed border-tint/10 bg-card/60 px-6 py-20 text-center">
                        <span className="grid h-16 w-16 place-items-center rounded-full bg-tint/[0.05]">
                            <CalendarX className="h-7 w-7 text-slate-400" />
                        </span>
                        <h3 className="mt-5 text-lg font-bold text-ink">No events found</h3>
                        <p className="mt-1 max-w-sm text-slate-500">Try another search or category — new events drop every week.</p>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3">
                            {events.data.map((e) => (
                                <EventCard key={e.id} event={e} />
                            ))}
                        </div>

                        {events.meta.last_page > 1 && (
                            <div className="mt-10 flex flex-wrap justify-center gap-1.5">
                                {events.links.map((link, i) =>
                                    link.url ? (
                                        <Link
                                            key={i}
                                            href={link.url}
                                            preserveScroll
                                            className={cn(
                                                'grid h-10 min-w-10 place-items-center rounded-full px-3.5 text-sm font-semibold transition-colors',
                                                link.active ? 'bg-brand-500 text-white glow-blue' : 'border border-tint/[0.08] bg-tint/[0.04] text-slate-600 hover:text-ink',
                                            )}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <span
                                            key={i}
                                            className="grid h-10 min-w-10 place-items-center rounded-full px-3.5 text-sm text-slate-300"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ),
                                )}
                            </div>
                        )}
                    </>
                )}
            </section>
        </PublicLayout>
    );
}

function Chip({ active, onClick, children }: { active: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <button
            onClick={onClick}
            className={cn(
                'inline-flex h-10 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition-colors',
                active ? 'border-brand-500 bg-brand-500 text-white' : 'border-tint/[0.08] bg-tint/[0.04] text-slate-600 hover:border-tint/20 hover:text-ink',
            )}
        >
            {children}
        </button>
    );
}
