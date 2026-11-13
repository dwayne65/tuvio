import { cn } from '@/lib/utils';
import { EventSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { AnimatePresence, motion, PanInfo, useReducedMotion } from 'framer-motion';
import { ArrowUpRight, ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

const INTERVAL = 4500;

function cover(event: EventSummary) {
    return event.cover_image_path
        ? { backgroundImage: `url(${event.cover_image_path})`, backgroundSize: 'cover', backgroundPosition: 'center' }
        : { background: `radial-gradient(120% 90% at 80% 0%, ${event.category?.color ?? '#6418f5'}, #0b0d14 70%)` };
}

function badge(event: EventSummary) {
    if (event.is_featured) return 'Spotlight';
    return new Date(event.start_at).getTime() <= Date.now() ? 'Happening now' : 'On sale';
}

function SpotlightPass({ event, onClickCapture }: { event: EventSummary; onClickCapture?: (e: React.MouseEvent) => void }) {
    return (
        <Link
            href={`/events/${event.slug}`}
            onClickCapture={onClickCapture}
            draggable={false}
            className="block select-none overflow-hidden rounded-[2rem] border border-tint/10 bg-card shadow-premium"
        >
            <div className="relative aspect-[4/3.4] overflow-hidden">
                <div className="absolute inset-0" style={cover(event)} />
                <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent" />
                <span className="absolute left-5 top-5 rounded-full border border-white/15 bg-black/30 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-white backdrop-blur-md">
                    {badge(event)}
                </span>
                {event.category && (
                    <span className="absolute right-5 top-5 rounded-full bg-white/90 px-3 py-1 text-[11px] font-bold text-[#0b0d12]">{event.category.name}</span>
                )}
                <h2 className="absolute inset-x-6 bottom-4 line-clamp-2 font-display text-[2.3rem] leading-[0.95] text-white sm:text-[2.6rem]">{event.title}</h2>
            </div>
            <div className="ticket-notch grid grid-cols-3 gap-2 border-t border-dashed border-tint/10 px-6 py-5">
                <PassFact label="Date" value={new Date(event.start_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} />
                <PassFact label="City" value={event.type === 'online' ? 'Online' : event.city ?? 'TBA'} />
                <PassFact label="From" value={event.is_free ? 'Free' : event.price_from?.formatted ?? '—'} />
            </div>
            <div className="flex items-center justify-between bg-brand-500 px-6 py-4 font-bold text-white">
                Get a ticket <ArrowUpRight className="h-5 w-5" />
            </div>
        </Link>
    );
}

function PassFact({ label, value }: { label: string; value: string }) {
    return (
        <div className="min-w-0">
            <p className="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">{label}</p>
            <p className="mt-1 truncate text-sm font-bold text-ink">{value}</p>
        </div>
    );
}

/**
 * Auto-advancing, swipeable stack of every live event. Pauses while hovered,
 * touched or focused, and stays still for visitors who prefer reduced motion.
 */
export function SpotlightCarousel({ events, className }: { events: EventSummary[]; className?: string }) {
    const [index, setIndex] = useState(0);
    const [direction, setDirection] = useState(1);
    const [paused, setPaused] = useState(false);
    const reduceMotion = useReducedMotion();
    const dragged = useRef(false);
    const count = events.length;

    const go = useCallback(
        (step: number) => {
            setDirection(step);
            setIndex((i) => (i + step + count) % count);
        },
        [count],
    );

    useEffect(() => {
        if (count < 2 || paused || reduceMotion) return;
        const id = setTimeout(() => go(1), INTERVAL);
        return () => clearTimeout(id);
    }, [index, paused, reduceMotion, count, go]);

    // Data can shrink between visits (e.g. an event ends); keep the index valid.
    useEffect(() => {
        if (index >= count) setIndex(0);
    }, [count, index]);

    if (count === 0) return null;

    const event = events[index % count];
    const behind = [1, 2].filter((n) => n < count).map((n) => events[(index + n) % count]);

    function onDragEnd(_: unknown, info: PanInfo) {
        if (Math.abs(info.offset.x) > 60 || Math.abs(info.velocity.x) > 400) {
            dragged.current = true;
            go(info.offset.x < 0 ? 1 : -1);
        }
    }

    return (
        <div
            className={cn('relative', className)}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            onTouchStart={() => setPaused(true)}
            onTouchEnd={() => setTimeout(() => setPaused(false), 2500)}
            onFocusCapture={() => setPaused(true)}
            onBlurCapture={() => setPaused(false)}
            aria-roledescription="carousel"
            aria-label="Live events"
        >
            <div className="relative">
                {/* Peeking tickets behind the current one */}
                {behind.map((e, i) => (
                    <motion.div
                        key={`${e.id}-behind`}
                        aria-hidden
                        className="absolute inset-x-0 top-0 h-full overflow-hidden rounded-[2rem] border border-tint/10 shadow-premium"
                        style={{ ...cover(e), zIndex: 1 - i }}
                        initial={false}
                        animate={{ y: (i + 1) * 14, scale: 1 - (i + 1) * 0.05, opacity: 0.55 - i * 0.2 }}
                        transition={{ type: 'spring', stiffness: 260, damping: 28 }}
                    />
                ))}

                <AnimatePresence initial={false} custom={direction} mode="popLayout">
                    <motion.div
                        key={event.id}
                        custom={direction}
                        className="relative z-10 cursor-grab active:cursor-grabbing"
                        variants={{
                            enter: (d: number) => ({ x: d * 70, opacity: 0, rotate: d * 3, scale: 0.96 }),
                            center: { x: 0, opacity: 1, rotate: 0, scale: 1 },
                            exit: (d: number) => ({ x: d * -90, opacity: 0, rotate: d * -4, scale: 0.94 }),
                        }}
                        initial="enter"
                        animate="center"
                        exit="exit"
                        transition={{ type: 'spring', stiffness: 300, damping: 30 }}
                        drag={count > 1 ? 'x' : false}
                        dragConstraints={{ left: 0, right: 0 }}
                        dragElastic={0.6}
                        onDragStart={() => (dragged.current = false)}
                        onDragEnd={onDragEnd}
                        aria-roledescription="slide"
                        aria-label={`${index + 1} of ${count}: ${event.title}`}
                    >
                        <SpotlightPass
                            event={event}
                            onClickCapture={(e) => {
                                // A swipe shouldn't also open the event.
                                if (dragged.current) {
                                    e.preventDefault();
                                    dragged.current = false;
                                }
                            }}
                        />
                    </motion.div>
                </AnimatePresence>
            </div>

            {count > 1 && (
                <div className="relative z-20 mt-9 flex items-center justify-between gap-3">
                    <button
                        type="button"
                        onClick={() => go(-1)}
                        aria-label="Previous event"
                        className="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-tint/10 bg-card text-ink shadow-[var(--shadow-card)] transition-transform active:scale-90"
                    >
                        <ChevronLeft className="h-5 w-5" />
                    </button>

                    {count <= 8 ? (
                        <div className="flex items-center gap-1.5">
                            {events.map((e, i) => (
                                <button
                                    key={e.id}
                                    type="button"
                                    onClick={() => {
                                        setDirection(i > index ? 1 : -1);
                                        setIndex(i);
                                    }}
                                    aria-label={`Show ${e.title}`}
                                    aria-current={i === index}
                                    className={cn('relative h-1.5 overflow-hidden rounded-full bg-tint/15 transition-all', i === index ? 'w-8' : 'w-1.5')}
                                >
                                    {i === index && (
                                        <motion.span
                                            key={`${index}-${paused}`}
                                            className="absolute inset-y-0 left-0 rounded-full bg-brand-500"
                                            initial={{ width: paused || reduceMotion ? '100%' : '0%' }}
                                            animate={{ width: '100%' }}
                                            transition={{ duration: paused || reduceMotion ? 0 : INTERVAL / 1000, ease: 'linear' }}
                                        />
                                    )}
                                </button>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm font-semibold tabular-nums text-slate-500">
                            <span className="text-ink">{index + 1}</span> / {count} live events
                        </p>
                    )}

                    <button
                        type="button"
                        onClick={() => go(1)}
                        aria-label="Next event"
                        className="grid h-10 w-10 shrink-0 place-items-center rounded-full border border-tint/10 bg-card text-ink shadow-[var(--shadow-card)] transition-transform active:scale-90"
                    >
                        <ChevronRight className="h-5 w-5" />
                    </button>
                </div>
            )}
        </div>
    );
}
