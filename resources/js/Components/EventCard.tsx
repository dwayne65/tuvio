import { TiltCard } from '@/Components/motion/TiltCard';
import { cn, formatDate } from '@/lib/utils';
import { EventSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, CalendarDays, MapPin } from 'lucide-react';

function priceLabel(event: EventSummary): string {
    if (event.is_free) {
        return 'Free';
    }
    return event.price_from ? event.price_from.formatted : 'On sale';
}

function placeLabel(event: EventSummary): string {
    return event.type === 'online' ? 'Online' : [event.venue_name, event.city].filter(Boolean).join(', ') || 'TBA';
}

function Cover({ event, className }: { event: EventSummary; className?: string }) {
    return event.cover_image_path ? (
        <img src={event.cover_image_path} alt="" loading="lazy" className={cn('h-full w-full object-cover', className)} />
    ) : (
        <div
            className={cn('relative h-full w-full overflow-hidden', className)}
            style={{ background: `radial-gradient(120% 90% at 80% 0%, ${event.category?.color ?? '#2f7dff'}, #0b0d14 70%)` }}
        >
            <div className="absolute -bottom-10 -left-6 h-40 w-40 rounded-full border-[18px] border-white/[0.07]" />
            <div className="absolute right-6 top-6 h-16 w-16 rounded-full bg-white/10 blur-xl" />
        </div>
    );
}

/** Poster-style event card. `compact` renders a horizontal list row. */
export function EventCard({ event, compact = false }: { event: EventSummary; compact?: boolean }) {
    const start = new Date(event.start_at);

    if (compact) {
        return (
            <Link
                href={`/events/${event.slug}`}
                className="group flex items-center gap-3.5 rounded-[1.4rem] border border-tint/[0.07] bg-card/80 p-2.5 pr-4 shadow-[var(--shadow-card)] transition-colors hover:border-tint/15 hover:bg-card-2"
            >
                <div className="relative h-[4.75rem] w-[4.75rem] shrink-0 overflow-hidden rounded-2xl">
                    <Cover event={event} className="transition-transform duration-500 group-hover:scale-110" />
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-[11px] font-bold uppercase tracking-[0.14em] text-brand-300">
                        {start.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}
                    </p>
                    <h3 className="mt-0.5 truncate text-[15px] font-bold text-ink">{event.title}</h3>
                    <p className="mt-0.5 flex items-center gap-1 truncate text-xs text-slate-400">
                        <MapPin className="h-3 w-3 shrink-0" /> {placeLabel(event)}
                    </p>
                </div>
                <span className="shrink-0 text-sm font-bold text-ink">{priceLabel(event)}</span>
            </Link>
        );
    }

    return (
        <TiltCard className="h-full rounded-[1.75rem]" max={4}>
            <Link
                href={`/events/${event.slug}`}
                className="group relative flex aspect-[4/5] h-full flex-col justify-end overflow-hidden rounded-[1.75rem] border border-white/[0.08] bg-card shadow-premium sm:aspect-[4/4.6]"
            >
                <Cover event={event} className="absolute inset-0 transition-transform duration-700 group-hover:scale-105" />
                <div className="absolute inset-0 bg-gradient-to-t from-[#07080c] via-[#07080c]/55 to-transparent" />

                <div className="absolute inset-x-4 top-4 flex items-start justify-between gap-2">
                    <div className="flex flex-wrap gap-1.5">
                        {event.is_featured && (
                            <span className="rounded-full bg-brand-500 px-2.5 py-1 text-[11px] font-bold text-white">Featured</span>
                        )}
                        {event.category && (
                            <span className="rounded-full border border-white/15 bg-black/30 px-2.5 py-1 text-[11px] font-semibold text-white backdrop-blur-md">
                                {event.category.name}
                            </span>
                        )}
                    </div>
                    <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-white/15 bg-black/35 text-center text-white backdrop-blur-md">
                        <span className="text-[10px] font-bold uppercase leading-none tracking-wide text-[#8db8ff]">
                            {start.toLocaleDateString('en-US', { month: 'short' })}
                        </span>
                        <span className="-mt-2 text-lg font-extrabold leading-none">{start.getDate()}</span>
                    </div>
                </div>

                <div className="relative p-4 sm:p-5">
                    <p className="truncate text-[11px] font-semibold uppercase tracking-[0.16em] text-white/55">
                        {event.organization?.name ?? 'TicketFlow picks'}
                    </p>
                    <h3 className="mt-1.5 line-clamp-2 font-display text-[1.85rem] leading-[1.02] text-white">{event.title}</h3>
                    <div className="mt-3 flex flex-col gap-1 text-[13px] text-white/75">
                        <span className="flex items-center gap-1.5">
                            <CalendarDays className="h-3.5 w-3.5 text-[#8db8ff]" />
                            {formatDate(event.start_at, { weekday: 'short' })}
                        </span>
                        <span className="flex items-center gap-1.5 truncate">
                            <MapPin className="h-3.5 w-3.5 shrink-0 text-[#8db8ff]" />
                            {placeLabel(event)}
                        </span>
                    </div>

                    <div className="mt-4 flex items-center justify-between gap-3 rounded-full border border-white/10 bg-white/[0.06] p-1.5 pl-4 backdrop-blur-md">
                        <span className="truncate text-sm font-bold text-white">
                            {!event.is_free && event.price_from && <span className="mr-1 text-xs font-medium text-white/55">from</span>}
                            {priceLabel(event)}
                        </span>
                        <span className="inline-flex shrink-0 items-center gap-1 rounded-full bg-brand-500 px-3.5 py-2 text-xs font-bold text-white glow-blue transition-transform group-hover:-translate-y-0.5">
                            Get tickets <ArrowUpRight className="h-3.5 w-3.5" />
                        </span>
                    </div>
                </div>
            </Link>
        </TiltCard>
    );
}
