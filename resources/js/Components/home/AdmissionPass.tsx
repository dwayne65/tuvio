import { Qr } from '@/Components/Qr';
import { Button } from '@/Components/ui/Button';
import { cn, formatDate } from '@/lib/utils';
import { EventSummary } from '@/types';
import { router } from '@inertiajs/react';
import { motion, useMotionValue, useReducedMotion, useSpring, useTransform } from 'framer-motion';
import { ArrowRight, CalendarDays, Clock, MapPin } from 'lucide-react';
import { PointerEvent, useEffect, useRef, useState } from 'react';

/**
 * A digital admission pass for a real upcoming event. It tilts toward the
 * pointer, and "Get tickets" tears the stub away before going to the event's
 * ticket section. Booking itself stays on the event page. The QR code here
 * links to the event, it is not a ticket.
 */
export function AdmissionPass({ event }: { event: EventSummary | null }) {
    const reduced = useReducedMotion();
    const ref = useRef<HTMLDivElement>(null);
    const [leaving, setLeaving] = useState(false);
    const [eventUrl, setEventUrl] = useState<string | null>(null);
    const px = useMotionValue(0.5);
    const py = useMotionValue(0.5);
    const rotateX = useSpring(useTransform(py, [0, 1], [10, -10]), { stiffness: 160, damping: 18 });
    const rotateY = useSpring(useTransform(px, [0, 1], [-14, 14]), { stiffness: 160, damping: 18 });
    const sheen = useTransform(px, [0, 1], ['-30%', '130%']);

    const href = event ? `/events/${event.slug}#tickets` : '/events';

    useEffect(() => {
        if (event) {
            setEventUrl(`${window.location.origin}/events/${event.slug}`);
        }
    }, [event]);

    function onMove(e: PointerEvent<HTMLDivElement>) {
        if (reduced || !ref.current) return;
        const r = ref.current.getBoundingClientRect();
        px.set((e.clientX - r.left) / r.width);
        py.set((e.clientY - r.top) / r.height);
    }

    function getTickets() {
        if (reduced) {
            router.visit(href);
            return;
        }
        setLeaving(true);
        window.setTimeout(() => router.visit(href), 650);
    }

    const start = event ? new Date(event.start_at) : null;
    const place = event ? (event.type === 'online' ? 'Online' : [event.venue_name, event.city].filter(Boolean).join(', ') || 'Venue TBA') : null;
    const price = event ? (event.is_free ? 'Free' : event.price_from?.formatted ?? 'On sale') : null;

    return (
        <div className="flex flex-col items-center">
            <div
                ref={ref}
                onPointerMove={onMove}
                onPointerLeave={() => {
                    px.set(0.5);
                    py.set(0.5);
                }}
                className="w-full max-w-[34rem] [perspective:1200px]"
            >
                <motion.div
                    style={reduced ? undefined : { rotateX, rotateY, transformStyle: 'preserve-3d' }}
                    animate={leaving ? { y: -18, scale: 1.03, rotateZ: -2 } : { y: 0, scale: 1, rotateZ: 0 }}
                    transition={{ type: 'spring', stiffness: 200, damping: 20 }}
                    className="relative flex flex-col overflow-visible drop-shadow-[0_40px_60px_rgb(54_14_150/0.45)] sm:flex-row"
                >
                    {/* Main part */}
                    <div className="relative min-h-[17rem] flex-1 overflow-hidden rounded-t-[1.75rem] bg-[#101126] text-white sm:rounded-l-[1.75rem] sm:rounded-tr-none">
                        {event?.cover_image_path && <img src={event.cover_image_path} alt="" loading="lazy" className="absolute inset-0 h-full w-full object-cover opacity-45" />}
                        <div className="absolute inset-0 bg-[radial-gradient(110%_90%_at_0%_0%,rgb(124_58_237/0.85),transparent_60%),linear-gradient(to_top,#101126,rgb(16_17_38/0.4))]" />
                        {!reduced && (
                            <motion.span
                                aria-hidden
                                className="pointer-events-none absolute inset-y-0 w-1/3 -skew-x-12 bg-gradient-to-r from-transparent via-white/20 to-transparent mix-blend-overlay"
                                style={{ left: sheen }}
                            />
                        )}
                        <div className="relative flex h-full flex-col p-6 sm:p-7">
                            <div className="flex items-center justify-between">
                                <img src="/brand/tuvio-logo-dark.png" alt="TUVIO" className="h-6 w-auto" draggable={false} />
                                <span className="rounded-full border border-white/25 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[0.2em]">Admit one</span>
                            </div>
                            <p className="mt-6 text-[11px] font-bold uppercase tracking-[0.2em] text-[#cbb8ff]">{event?.category?.name ?? 'Your next experience'}</p>
                            <h3 className="mt-1.5 line-clamp-2 font-display text-[2.1rem] leading-[1] sm:text-[2.4rem]">{event?.title ?? 'Your next unforgettable night'}</h3>
                            <div className="mt-auto grid gap-1.5 pt-6 text-[13px] text-white/80">
                                {start ? (
                                    <>
                                        <span className="flex items-center gap-2"><CalendarDays className="h-3.5 w-3.5 text-[#cbb8ff]" /> {formatDate(event!.start_at, { weekday: 'short' })}</span>
                                        <span className="flex items-center gap-2"><Clock className="h-3.5 w-3.5 text-[#cbb8ff]" /> {start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' })}</span>
                                        <span className="flex items-center gap-2 truncate"><MapPin className="h-3.5 w-3.5 shrink-0 text-[#cbb8ff]" /> {place}</span>
                                    </>
                                ) : (
                                    <span>New events are added every week.</span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Perforation */}
                    <div aria-hidden className="relative h-0 sm:h-auto sm:w-0">
                        <span className="absolute -left-3 -top-3 z-10 h-6 w-6 rounded-full bg-canvas" />
                        <span className="absolute -right-3 -top-3 z-10 h-6 w-6 rounded-full bg-canvas sm:-bottom-3 sm:-left-3 sm:right-auto sm:top-auto" />
                    </div>

                    {/* Stub */}
                    <motion.div
                        animate={leaving ? { x: 26, y: 10, rotateZ: 7, opacity: 0.9 } : { x: 0, y: 0, rotateZ: 0, opacity: 1 }}
                        transition={{ type: 'spring', stiffness: 180, damping: 16 }}
                        className="relative flex items-center gap-4 rounded-b-[1.75rem] border-t-2 border-dashed border-[#101126]/15 bg-[#f7f8fc] p-5 text-[#101126] sm:w-48 sm:flex-col sm:justify-center sm:rounded-r-[1.75rem] sm:rounded-bl-none sm:border-l-2 sm:border-t-0"
                    >
                        <div className="rounded-xl bg-white p-1.5 shadow-sm">
                            {eventUrl ? <Qr value={eventUrl} size={112} /> : <QrPattern />}
                        </div>
                        <div className="sm:text-center">
                            <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-[#6415F5]">{event && !event.is_free ? 'From' : 'Entry'}</p>
                            <p className="text-lg font-extrabold tracking-tight">{price ?? 'Find yours'}</p>
                            <p className="mt-0.5 text-[11px] text-slate-500">{event ? 'Scan to view event' : 'Browse what’s on'}</p>
                        </div>
                    </motion.div>
                </motion.div>
            </div>

            <Button size="lg" onClick={getTickets} disabled={leaving} className="mt-9 min-w-56">
                {event ? 'Get tickets' : 'Discover events'} <ArrowRight className={cn('h-4 w-4 transition-transform', leaving && 'translate-x-1')} />
            </Button>
        </div>
    );
}

/** Placeholder while the real QR renders, or when there is no event to link to. */
function QrPattern() {
    const cells = Array.from({ length: 81 }, (_, i) => ((i * 37 + (i % 9) * 11) % 5 < 2 ? 1 : 0));
    return (
        <div className="grid h-[112px] w-[112px] grid-cols-9 gap-[2px] p-1">
            {cells.map((on, i) => (
                <span key={i} className={on ? 'rounded-[1px] bg-[#101126]' : ''} />
            ))}
        </div>
    );
}
