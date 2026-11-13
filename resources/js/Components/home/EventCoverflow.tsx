import { categoryIcon } from '@/Components/CategoryIcon';
import { cn } from '@/lib/utils';
import { Category, EventSummary } from '@/types';
import { Link } from '@inertiajs/react';
import { motion, useMotionValue, useReducedMotion, useSpring, useTransform } from 'framer-motion';
import { ArrowUpRight, CalendarDays, ChevronLeft, ChevronRight, MapPin } from 'lucide-react';
import { PointerEvent, ReactNode, useEffect, useRef, useState } from 'react';

/**
 * Live events as posters on a 3D stage: the front poster opens the event,
 * side posters rotate into place, and the whole stage leans toward the
 * pointer. Categories fill any empty places, so it never shows made-up events.
 */

type Slide =
    | { kind: 'event'; key: string; event: EventSummary }
    | { kind: 'category'; key: string; category: Category };

const AUTO_ADVANCE_MS = 4500;
const VISIBLE_SIDE = 2;
const MIN_SLIDES = 5;

/** Signed distance from the active slide, wrapping around both ends. */
function offsetFrom(index: number, active: number, count: number): number {
    let d = index - active;
    if (d > count / 2) d -= count;
    if (d < -count / 2) d += count;
    return d;
}

export function EventCoverflow({ events, categories, className }: { events: EventSummary[]; categories: Category[]; className?: string }) {
    const reduced = useReducedMotion();
    // Real events first; categories fill the stage so it stays balanced when few events are live.
    const slides: Slide[] = [
        ...events.slice(0, 7).map((event): Slide => ({ kind: 'event', key: `e${event.id}`, event })),
        ...categories.slice(0, Math.max(0, MIN_SLIDES - events.length)).map((category): Slide => ({ kind: 'category', key: `c${category.id}`, category })),
    ];
    const count = slides.length;
    const [active, setActive] = useState(0);
    const [paused, setPaused] = useState(false);
    const stage = useRef<HTMLDivElement>(null);
    const px = useMotionValue(0.5);
    const py = useMotionValue(0.5);
    const tiltX = useSpring(useTransform(py, [0, 1], [7, -7]), { stiffness: 120, damping: 20 });
    const tiltY = useSpring(useTransform(px, [0, 1], [-10, 10]), { stiffness: 120, damping: 20 });

    const go = (step: number) => setActive((current) => (current + step + count) % count);

    useEffect(() => {
        if (reduced || paused || count < 2) return;
        const id = window.setInterval(() => setActive((current) => (current + 1) % count), AUTO_ADVANCE_MS);
        return () => window.clearInterval(id);
    }, [reduced, paused, count]);

    function onMove(e: PointerEvent<HTMLDivElement>) {
        if (reduced || !stage.current || e.pointerType !== 'mouse') return;
        const r = stage.current.getBoundingClientRect();
        px.set((e.clientX - r.left) / r.width);
        py.set((e.clientY - r.top) / r.height);
    }

    if (count === 0) return null;

    return (
        <div className={cn('relative', className)}>
            <div
                ref={stage}
                onPointerMove={onMove}
                onPointerEnter={() => setPaused(true)}
                onPointerLeave={() => {
                    setPaused(false);
                    px.set(0.5);
                    py.set(0.5);
                }}
                onFocusCapture={() => setPaused(true)}
                onBlurCapture={() => setPaused(false)}
                className="relative h-[25rem] [perspective:1600px] sm:h-[30rem]"
                role="region"
                aria-roledescription="carousel"
                aria-label="Featured events"
            >
                {/* Floor glow the posters stand on */}
                <div aria-hidden className="absolute inset-x-[8%] bottom-0 h-16 rounded-[50%] bg-[radial-gradient(closest-side,rgb(124_58_237/0.55),transparent)] blur-xl" />
                <div aria-hidden className="absolute left-1/2 top-1/2 h-[70%] w-[70%] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#6415F5]/25 blur-[90px]" />

                <motion.div className="relative h-full w-full" style={reduced ? undefined : { rotateX: tiltX, rotateY: tiltY, transformStyle: 'preserve-3d' }}>
                    {slides.map((slide, index) => {
                        const d = offsetFrom(index, active, count);
                        const distance = Math.abs(d);
                        const hidden = distance > VISIBLE_SIDE;
                        const isFront = d === 0;

                        return (
                            <motion.div
                                key={slide.key}
                                initial={false}
                                animate={{
                                    x: `${d * 52}%`,
                                    z: -distance * 170,
                                    rotateY: -d * 34,
                                    scale: 1 - distance * 0.07,
                                    opacity: hidden ? 0 : 1,
                                }}
                                transition={reduced ? { duration: 0 } : { type: 'spring', stiffness: 140, damping: 22, mass: 0.9 }}
                                style={{ zIndex: 10 - distance, transformStyle: 'preserve-3d' }}
                                className={cn(
                                    'absolute left-1/2 top-2 -ml-[8.5rem] w-[17rem] sm:-ml-[9.75rem] sm:w-[19.5rem]',
                                    hidden && 'pointer-events-none',
                                )}
                                aria-hidden={!isFront}
                            >
                                {slide.kind === 'event' ? (
                                    <EventPoster event={slide.event} front={isFront} onSelect={() => setActive(index)} />
                                ) : (
                                    <CategoryPoster category={slide.category} front={isFront} onSelect={() => setActive(index)} />
                                )}
                                {/* Side posters sit out of the light; shading keeps them opaque so cards never ghost through each other */}
                                <motion.div
                                    aria-hidden
                                    initial={false}
                                    animate={{ opacity: Math.min(distance, VISIBLE_SIDE) * 0.32 }}
                                    transition={reduced ? { duration: 0 } : { duration: 0.5 }}
                                    className="pointer-events-none absolute inset-0 rounded-[1.75rem] bg-[#07060f]"
                                />
                            </motion.div>
                        );
                    })}
                </motion.div>
            </div>

            {count > 1 && (
                <div className="mt-5 flex items-center justify-center gap-3">
                    <StageButton label="Previous" onClick={() => go(-1)}><ChevronLeft className="h-4 w-4" /></StageButton>
                    <div className="flex items-center gap-1.5">
                        {slides.map((slide, index) => (
                            <button
                                key={slide.key}
                                type="button"
                                onClick={() => setActive(index)}
                                aria-label={`Show ${slide.kind === 'event' ? slide.event.title : slide.category.name}`}
                                aria-current={index === active}
                                className={cn('h-1.5 rounded-full transition-all duration-300', index === active ? 'w-6 bg-white' : 'w-1.5 bg-white/30 hover:bg-white/60')}
                            />
                        ))}
                    </div>
                    <StageButton label="Next" onClick={() => go(1)}><ChevronRight className="h-4 w-4" /></StageButton>
                </div>
            )}
        </div>
    );
}

function StageButton({ label, onClick, children }: { label: string; onClick: () => void; children: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className="grid h-9 w-9 place-items-center rounded-full border border-white/15 bg-white/[0.07] text-white backdrop-blur transition-colors hover:bg-white/15"
        >
            {children}
        </button>
    );
}

/** Shared poster frame: side posters are buttons that bring themselves forward. */
function PosterFrame({ href, front, onSelect, label, children }: { href: string; front: boolean; onSelect: () => void; label: string; children: ReactNode }) {
    const frame = cn(
        'group relative block aspect-[3/4.1] overflow-hidden rounded-[1.75rem] border text-left text-white transition-shadow duration-500',
        front
            ? 'border-white/20 shadow-[0_40px_80px_-30px_rgb(100_21_245/0.8),0_0_0_1px_rgb(255_255_255/0.04)]'
            : 'border-white/10 shadow-[0_30px_60px_-30px_rgb(0_0_0/0.9)]',
    );

    return front ? (
        <Link href={href} className={frame}>
            {children}
        </Link>
    ) : (
        <button type="button" onClick={onSelect} className={cn(frame, 'w-full')} aria-label={label} tabIndex={-1}>
            {children}
        </button>
    );
}

function EventPoster({ event, front, onSelect }: { event: EventSummary; front: boolean; onSelect: () => void }) {
    const start = new Date(event.start_at);
    const place = event.type === 'online' ? 'Online' : [event.venue_name, event.city].filter(Boolean).join(', ') || 'Venue TBA';
    const price = event.is_free ? 'Free' : event.price_from?.formatted ?? 'On sale';
    const color = event.category?.color ?? '#6415F5';

    return (
        <PosterFrame href={`/events/${event.slug}`} front={front} onSelect={onSelect} label={`Show ${event.title}`}>
            {event.cover_image_path ? (
                <img src={event.cover_image_path} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" />
            ) : (
                <div className="absolute inset-0" style={{ background: `radial-gradient(120% 90% at 80% 0%, ${color}, #0b0a1f 72%)` }} />
            )}
            <div className="absolute inset-0 bg-gradient-to-t from-[#07060f] via-[#07060f]/55 to-transparent" />
            {/* Glass sheen across the poster */}
            <div className="pointer-events-none absolute inset-0 bg-[linear-gradient(115deg,transparent_30%,rgb(255_255_255/0.12)_45%,transparent_60%)] opacity-0 transition-opacity duration-500 group-hover:opacity-100" />

            <div className="absolute inset-x-4 top-4 flex items-start justify-between gap-2">
                {event.category ? (
                    <span className="rounded-full border border-white/15 bg-black/30 px-2.5 py-1 text-[11px] font-semibold backdrop-blur-md">{event.category.name}</span>
                ) : (
                    <span />
                )}
                <span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl border border-white/15 bg-black/35 text-center backdrop-blur-md">
                    <span className="text-[10px] font-bold uppercase leading-none tracking-wide text-[#cbb8ff]">{start.toLocaleDateString('en-US', { month: 'short' })}</span>
                    <span className="-mt-2 text-lg font-extrabold leading-none">{start.getDate()}</span>
                </span>
            </div>

            <div className="absolute inset-x-0 bottom-0 p-5">
                {event.availability === 'selling_fast' && (
                    <span className="mb-2 inline-flex items-center gap-1.5 rounded-full bg-amber-400/15 px-2.5 py-1 text-[11px] font-bold text-amber-200">
                        <span className="h-1.5 w-1.5 rounded-full bg-amber-400" /> Selling fast
                    </span>
                )}
                <h3 className="line-clamp-2 font-display text-[1.9rem] leading-[1.02]">{event.title}</h3>
                <div className="mt-2.5 grid gap-1 text-[12.5px] text-white/75">
                    <span className="flex items-center gap-1.5">
                        <CalendarDays className="h-3.5 w-3.5 text-[#cbb8ff]" />
                        {start.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })} · {start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}
                    </span>
                    <span className="flex items-center gap-1.5 truncate"><MapPin className="h-3.5 w-3.5 shrink-0 text-[#cbb8ff]" /> {place}</span>
                </div>
                <div className="mt-4 flex items-center justify-between gap-3 rounded-full border border-white/10 bg-white/[0.07] p-1.5 pl-4 backdrop-blur-md">
                    <span className="truncate text-sm font-bold">
                        {!event.is_free && event.price_from && <span className="mr-1 text-xs font-medium text-white/55">from</span>}
                        {price}
                    </span>
                    <span className={cn('inline-flex shrink-0 items-center gap-1 rounded-full px-3.5 py-2 text-xs font-bold', event.availability === 'sold_out' ? 'bg-white/15' : 'bg-[#6415F5]')}>
                        {event.availability === 'sold_out' ? 'Sold out' : 'Get tickets'} <ArrowUpRight className="h-3.5 w-3.5" />
                    </span>
                </div>
            </div>
        </PosterFrame>
    );
}

function CategoryPoster({ category, front, onSelect }: { category: Category; front: boolean; onSelect: () => void }) {
    const Icon = categoryIcon(category.icon);
    const color = category.color ?? '#6415F5';

    return (
        <PosterFrame href={`/events?category=${category.slug}`} front={front} onSelect={onSelect} label={`Show ${category.name}`}>
            <div className="absolute inset-0" style={{ background: `radial-gradient(110% 80% at 80% 5%, ${color}, #0b0a1f 70%)` }} />
            <Icon aria-hidden className="absolute -right-6 top-6 h-48 w-48 text-white/[0.12]" />
            <div className="absolute inset-x-0 bottom-0 p-6">
                <span className="grid h-11 w-11 place-items-center rounded-xl border border-white/20 bg-white/10 backdrop-blur"><Icon className="h-5 w-5" /></span>
                <h3 className="mt-4 font-display text-[2rem] leading-[1.02]">{category.name}</h3>
                <p className="mt-1 text-sm text-white/70">Explore what’s on</p>
                <span className="mt-4 inline-flex items-center gap-1 rounded-full bg-[#6415F5] px-3.5 py-2 text-xs font-bold">
                    Browse <ArrowUpRight className="h-3.5 w-3.5" />
                </span>
            </div>
        </PosterFrame>
    );
}
