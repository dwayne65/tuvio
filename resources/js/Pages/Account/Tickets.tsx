import { Qr } from '@/Components/Qr';
import { Button } from '@/Components/ui/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { cn, formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { Check, Download, Receipt, ScanLine, ShieldCheck, Ticket as TicketIcon, X } from 'lucide-react';
import { CSSProperties, useEffect, useId, useState } from 'react';

/** The category design, sent by the server from App\Support\TicketDesign (the PDF and email use the same one). */
interface Design {
    key: 'ordinary' | 'vip' | 'vvip' | 'guest';
    label: string;
    badge: string;
    glyph: string;
    privilege: string;
    dark: boolean;
    pattern: 'grid' | 'deco' | 'guilloche' | 'pinstripe';
    holo: boolean;
    complimentary: boolean;
    base: string;
    base2: string;
    ink: string;
    muted: string;
    accent: string;
    accent2: string;
    metal: [string, string, string];
    stubFrom: string;
    stubTo: string;
    stubInk: string;
    stubMuted: string;
    rule: string;
}

interface T {
    code: string;
    qr_token: string;
    status: string;
    attendee_name: string | null;
    type: string;
    tier: string;
    number: string;
    design: Design;
    perks: string[];
    price_minor: number;
    currency: string;
    used_at: string | null;
    event: {
        title: string;
        slug: string;
        cover_url: string | null;
        start_at: string | null;
        venue_name: string | null;
        city: string | null;
        venue_address: string | null;
        organizer: string | null;
        category: string | null;
        type: string;
    };
}

function isPast(t: T): boolean {
    return t.status !== 'issued' || (t.event.start_at !== null && new Date(t.event.start_at) < new Date());
}

/** A hex colour at the given opacity. */
function alpha(hex: string, opacity: number): string {
    const value = parseInt(hex.slice(1), 16);

    return `rgb(${(value >> 16) & 255} ${(value >> 8) & 255} ${value & 255} / ${opacity})`;
}

function metal(d: Design, angle = 100): string {
    return `linear-gradient(${angle}deg, ${d.metal[0]}, ${d.metal[1]} 45%, ${d.metal[2]})`;
}

/** Text filled with the category's metal gradient. */
function metalText(d: Design): CSSProperties {
    return { backgroundImage: metal(d), WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' };
}

function panelBackground(d: Design): CSSProperties {
    return {
        background: `radial-gradient(90% 120% at 0% 0%, ${alpha(d.accent, d.dark ? 0.16 : 0.06)}, transparent 55%), radial-gradient(70% 90% at 100% 100%, ${alpha(d.accent2, d.dark ? 0.14 : 0.12)}, transparent 60%), linear-gradient(135deg, ${d.base}, ${d.base2})`,
    };
}

function stubBackground(d: Design): CSSProperties {
    return {
        background: `radial-gradient(110% 80% at 100% 0%, ${alpha(d.accent, 0.22)}, transparent 60%), radial-gradient(90% 70% at 0% 100%, ${alpha(d.accent2, 0.14)}, transparent 60%), linear-gradient(160deg, ${d.stubFrom}, ${d.stubTo})`,
    };
}

function ticketPrice(t: T): string {
    return t.price_minor === 0 ? 'Complimentary' : money(t.price_minor, t.currency);
}

function eventDate(t: T): string {
    return t.event.start_at ? formatDate(t.event.start_at, { weekday: 'short' }) : 'TBA';
}

function eventTime(t: T): string {
    return t.event.start_at ? new Date(t.event.start_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : 'TBA';
}

function eventVenue(t: T): string {
    return t.event.type === 'online' ? 'Online event' : t.event.venue_name || 'Venue to be announced';
}

function eventLocation(t: T): string {
    return t.event.type === 'online' ? 'Link sent by the organizer' : [t.event.venue_address, t.event.city].filter(Boolean).join(', ') || '—';
}

/*
 | Tear-off notches. Narrow cards stack vertically (notches on the left/right
 | edges); from 40rem of card width they lay out horizontally like a paper
 | ticket (notches on the top/bottom edges at the perforation).
*/
const MAIN_MASK =
    '[mask:radial-gradient(circle_10px_at_0_100%,#0000_97%,#000)_left/51%_100%_no-repeat,radial-gradient(circle_10px_at_100%_100%,#0000_97%,#000)_right/51%_100%_no-repeat] @min-[40rem]:[mask:radial-gradient(circle_10px_at_100%_0,#0000_97%,#000)_top/100%_51%_no-repeat,radial-gradient(circle_10px_at_100%_100%,#0000_97%,#000)_bottom/100%_51%_no-repeat]';
const STUB_MASK =
    '[mask:radial-gradient(circle_10px_at_0_0,#0000_97%,#000)_left/51%_100%_no-repeat,radial-gradient(circle_10px_at_100%_0,#0000_97%,#000)_right/51%_100%_no-repeat] @min-[40rem]:[mask:radial-gradient(circle_10px_at_0_0,#0000_97%,#000)_top/100%_51%_no-repeat,radial-gradient(circle_10px_at_0_100%,#0000_97%,#000)_bottom/100%_51%_no-repeat]';

/** Vertical-pass notches at the top or bottom corners of a section. */
function notchMask(edge: 'top' | 'bottom'): CSSProperties {
    const y = edge === 'top' ? '0' : '100%';
    const mask = `radial-gradient(circle 11px at 0 ${y}, #0000 97%, #000) left / 51% 100% no-repeat, radial-gradient(circle 11px at 100% ${y}, #0000 97%, #000) right / 51% 100% no-repeat`;

    return { mask, WebkitMask: mask };
}

const passShadow: CSSProperties = { filter: 'drop-shadow(0 1px 1px rgb(17 24 39 / 0.08)) drop-shadow(0 26px 34px rgb(17 24 39 / 0.22))' };

interface StatusStyle {
    label: string;
    className: string;
    dot: string;
}

/** Maps the ticket's existing status (plus whether the event has passed) to a display style. */
function statusStyle(t: T): StatusStyle {
    switch (t.status) {
        case 'used':
            return { label: 'Checked in', className: 'bg-[#E4E0FF] text-[#3C2A99]', dot: 'bg-[#5B3FD6]' };
        case 'refunded':
            return { label: 'Refunded', className: 'bg-amber-100 text-amber-800', dot: 'bg-amber-500' };
        case 'void':
            return { label: 'Cancelled', className: 'bg-rose-100 text-rose-700', dot: 'bg-rose-500' };
        case 'issued':
            return isPast(t)
                ? { label: 'Expired', className: 'bg-[#EEF0F4] text-[#475569]', dot: 'bg-[#94a3b8]' }
                : { label: 'Valid', className: 'bg-[#1FB86A] text-[#04220F]', dot: 'bg-[#04220F]' };
        default:
            return { label: t.status.charAt(0).toUpperCase() + t.status.slice(1), className: 'bg-[#EEF0F4] text-[#475569]', dot: 'bg-[#94a3b8]' };
    }
}

export default function Tickets({ tickets }: PageProps<{ tickets: T[] }>) {
    const [tab, setTab] = useState<'upcoming' | 'history'>('upcoming');
    const [active, setActive] = useState<T | null>(null);
    const shown = tickets.filter((t) => (tab === 'history' ? isPast(t) : !isPast(t)));

    useEffect(() => {
        if (!active) {
            return;
        }
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setActive(null);
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [active]);

    return (
        <PublicLayout>
            <Head title="My Tickets" />

            <div className="container-page pt-3 md:pt-12">
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-300">Wallet</p>
                        <h1 className="mt-1 text-[2rem] font-extrabold tracking-[-0.045em] text-ink md:text-4xl">
                            My <span className="font-display font-normal italic tracking-normal text-gradient">tickets</span>
                        </h1>
                    </div>
                    <Link
                        href="/my-account/orders"
                        className="mb-1 inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full border border-tint/10 bg-tint/[0.04] px-4 text-sm font-semibold text-ink transition-colors hover:bg-tint/[0.08]"
                    >
                        <Receipt className="h-4 w-4 text-slate-500" /> Orders
                    </Link>
                </div>

                <div className="mt-5 inline-flex rounded-full border border-tint/[0.08] bg-tint/[0.03] p-1">
                    {(['upcoming', 'history'] as const).map((key) => (
                        <button
                            key={key}
                            onClick={() => setTab(key)}
                            className={cn(
                                'rounded-full px-5 py-2 text-sm font-semibold transition-colors',
                                tab === key ? 'bg-brand-500 text-white glow-blue' : 'text-slate-500 hover:text-ink',
                            )}
                        >
                            {key === 'upcoming' ? 'Coming soon' : 'History'}
                        </button>
                    ))}
                </div>

                {shown.length === 0 ? (
                    <div className="mt-6 flex flex-col items-center rounded-[2rem] border border-dashed border-tint/10 bg-card/60 px-6 py-16 text-center">
                        <span className="grid h-16 w-16 place-items-center rounded-full bg-tint/[0.05]">
                            <TicketIcon className="h-7 w-7 text-slate-400" />
                        </span>
                        <h3 className="mt-5 text-lg font-bold text-ink">{tab === 'upcoming' ? 'No upcoming tickets' : 'Nothing here yet'}</h3>
                        <p className="mt-1 text-slate-500">{tab === 'upcoming' ? 'Tickets you buy will appear here.' : 'Past and used tickets will show up here.'}</p>
                        <Button href="/events" className="mt-6">Discover events</Button>
                    </div>
                ) : (
                    <div className="mt-6 grid max-w-5xl gap-7">
                        {shown.map((t, i) => (
                            <motion.div key={t.code} initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: i * 0.05 }}>
                                <TicketCard ticket={t} onOpen={() => setActive(t)} />
                            </motion.div>
                        ))}
                    </div>
                )}
            </div>

            {/* QR pass: bottom sheet on phones, dialog on desktop */}
            <AnimatePresence>
                {active && (
                    <motion.div
                        className="fixed inset-0 z-[60] flex items-end justify-center bg-[#0b0f1a]/75 backdrop-blur-sm sm:items-center sm:p-4"
                        onClick={() => setActive(null)}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                    >
                        <motion.div
                            role="dialog"
                            aria-modal="true"
                            aria-label={`Ticket for ${active.event.title}`}
                            className="max-h-[92dvh] w-full max-w-[25rem] overflow-y-auto overscroll-contain sm:max-h-[calc(100dvh-2rem)]"
                            onClick={(e) => e.stopPropagation()}
                            initial={{ y: 60 }}
                            animate={{ y: 0 }}
                            exit={{ y: 60 }}
                            transition={{ type: 'spring', stiffness: 380, damping: 34 }}
                        >
                            <TicketPass ticket={active} onClose={() => setActive(null)} />
                        </motion.div>
                    </motion.div>
                )}
            </AnimatePresence>
        </PublicLayout>
    );
}

function StatusPill({ ticket, className }: { ticket: T; className?: string }) {
    const status = statusStyle(ticket);

    return (
        <span className={cn('inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-[0.14em]', status.className, className)}>
            <span className={cn('h-1.5 w-1.5 rounded-full', status.dot)} />
            {status.label}
        </span>
    );
}

function Brand({ d }: { d: Design }) {
    return (
        <span className="inline-flex items-center gap-2">
            <img src="/brand/tuvio-mark.png" alt="" className="h-5 w-auto" />
            <span className="text-[10px] font-extrabold uppercase tracking-[0.26em]" style={{ color: d.ink }}>
                TUVIO
            </span>
            <span className="text-[9px] font-semibold uppercase tracking-[0.24em]" style={{ color: d.muted }}>
                presents
            </span>
        </span>
    );
}

function Eyebrow({ children, color, className }: { children: React.ReactNode; color: string; className?: string }) {
    return (
        <p className={cn('text-[9.5px] font-extrabold uppercase tracking-[0.2em]', className)} style={{ color }}>
            {children}
        </p>
    );
}

function Field({ label, value, d, strong, className }: { label: string; value: string; d: Design; strong?: boolean; className?: string }) {
    return (
        <div className={cn('min-w-0', className)}>
            <Eyebrow color={d.muted}>{label}</Eyebrow>
            <p className={cn('mt-1 truncate leading-tight', strong ? 'text-[15px] font-extrabold' : 'text-sm font-semibold')} style={{ color: d.ink }}>
                {value}
            </p>
        </div>
    );
}

/** The category's signature line pattern (the same motifs the PDF draws). */
function Pattern({ d }: { d: Design }) {
    const id = useId().replace(/:/g, '');

    return (
        <svg aria-hidden className="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 448 259" preserveAspectRatio="xMinYMax slice">
            {d.pattern === 'grid' && (
                <>
                    <defs>
                        <pattern id={`dots${id}`} width="11" height="11" patternUnits="userSpaceOnUse">
                            <circle cx="5.5" cy="5.5" r="0.7" fill="#fff" />
                        </pattern>
                        <linearGradient id={`fade${id}`} x1="0" x2="1">
                            <stop offset="0" stopColor="#fff" stopOpacity="0.18" />
                            <stop offset="0.6" stopColor="#fff" stopOpacity="0" />
                        </linearGradient>
                        <mask id={`m${id}`}>
                            <rect width="448" height="259" fill={`url(#fade${id})`} />
                        </mask>
                    </defs>
                    <rect width="448" height="259" fill={`url(#dots${id})`} mask={`url(#m${id})`} />
                    {[0, 9, 18].map((o, i) => (
                        <line key={o} x1={224 + o} y1="0" x2={154 + o} y2="259" stroke={d.accent} strokeOpacity={0.5 - i * 0.15} strokeWidth="0.7" />
                    ))}
                </>
            )}
            {d.pattern === 'deco' && (
                <>
                    {Array.from({ length: 27 }, (_, i) => {
                        const a = (i / 26) * (Math.PI / 2);
                        return <line key={i} x1="0" y1="259" x2={Math.cos(a) * 336} y2={259 - Math.sin(a) * 336} stroke={d.accent} strokeOpacity="0.08" strokeWidth="0.6" />;
                    })}
                    {[60, 66, 120, 126, 180].map((r, i) => (
                        <path key={r} d={`M ${r} 259 A ${r} ${r} 0 0 0 0 ${259 - r}`} fill="none" stroke={d.accent} strokeOpacity={i % 2 ? 0.16 : 0.3} strokeWidth="0.7" />
                    ))}
                </>
            )}
            {d.pattern === 'guilloche' && (
                <>
                    {[
                        [78, 22, 9, d.accent, 0.16],
                        [64, 18, 11, d.accent2, 0.26],
                        [50, 14, 13, d.accent, 0.16],
                    ].map(([big, small, petals, color, opacity]) => (
                        <polyline
                            key={String(big)}
                            fill="none"
                            stroke={color as string}
                            strokeOpacity={opacity as number}
                            strokeWidth="0.5"
                            points={Array.from({ length: 361 }, (_, s) => {
                                const t = (s / 360) * Math.PI * 2;
                                const r = (big as number) + (small as number) * Math.sin((petals as number) * t);
                                return `${(89.6 + r * Math.cos(t)).toFixed(1)},${(212 + r * Math.sin(t) * 0.82).toFixed(1)}`;
                            }).join(' ')}
                        />
                    ))}
                    {Array.from({ length: 6 }, (_, row) => (
                        <polyline
                            key={row}
                            fill="none"
                            stroke={d.accent}
                            strokeOpacity="0.07"
                            strokeWidth="0.4"
                            points={Array.from({ length: 76 }, (_, k) => `${k * 6},${(16 + row * 4 + 2.2 * Math.sin((k * 6) / 14 + row)).toFixed(1)}`).join(' ')}
                        />
                    ))}
                </>
            )}
            {d.pattern === 'pinstripe' && (
                <>
                    {Array.from({ length: 9 }, (_, i) => {
                        const r = 26 * (i + 1);
                        return <path key={r} d={`M ${246 - r} 0 A ${r} ${r} 0 0 0 246 ${r}`} fill="none" stroke={d.accent} strokeOpacity="0.08" strokeWidth="0.6" />;
                    })}
                </>
            )}
        </svg>
    );
}

/** The event cover blended into the right of the panel, washed out on light designs. */
function Cover({ ticket: t, d, dimmed }: { ticket: T; d: Design; dimmed: boolean }) {
    if (!t.event.cover_url) {
        return null;
    }
    const fade = 'linear-gradient(90deg, transparent 0%, #000 70%)';

    return (
        <div aria-hidden className="absolute inset-y-0 right-0 w-[62%] @max-[40rem]:inset-x-0 @max-[40rem]:bottom-auto @max-[40rem]:h-48 @max-[40rem]:w-full">
            <img
                src={t.event.cover_url}
                alt=""
                className={cn('h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.03]', dimmed && 'grayscale')}
                style={{ maskImage: fade, WebkitMaskImage: fade, opacity: d.dark ? 0.78 : 0.45 }}
            />
            <div
                className="absolute inset-0 @max-[40rem]:hidden"
                style={{ background: `linear-gradient(90deg, ${d.base} 0%, ${alpha(d.base, 0.25)} 55%, ${alpha(d.base, d.dark ? 0.35 : 0.5)} 100%)` }}
            />
            <div className="absolute inset-0 @min-[40rem]:hidden" style={{ background: `linear-gradient(180deg, ${alpha(d.base, 0.1)} 0%, ${d.base} 100%)` }} />
        </div>
    );
}

/** Slow light sweep across premium passes (skipped when the visitor prefers reduced motion). */
function HoloSheen() {
    const reduceMotion = useReducedMotion();

    if (reduceMotion) {
        return null;
    }

    return (
        <motion.div
            aria-hidden
            className="pointer-events-none absolute inset-y-0 z-30 w-1/3 -skew-x-12"
            style={{ background: 'linear-gradient(90deg, transparent, rgb(255 255 255 / 0.08), rgb(255 220 250 / 0.06), transparent)' }}
            initial={{ left: '-40%' }}
            animate={{ left: '140%' }}
            transition={{ duration: 3.4, repeat: Infinity, repeatDelay: 3, ease: 'easeInOut' }}
        />
    );
}

/** Iridescent seal with the category glyph, for VIP and VVIP. */
function HoloSeal({ d, className }: { d: Design; className?: string }) {
    return (
        <span
            aria-hidden
            className={cn('grid h-9 w-9 place-items-center rounded-full p-[3px]', className)}
            style={{ background: `conic-gradient(from 30deg, ${d.metal[0]}, ${d.metal[1]}, #ffc4ec, #c3d3ff, ${d.metal[1]}, ${d.metal[2]}, #c8ffef, ${d.metal[0]})` }}
        >
            <span className="grid h-full w-full place-items-center rounded-full text-sm" style={{ background: d.stubTo, color: d.metal[1] }}>
                {d.glyph}
            </span>
        </span>
    );
}

/** Category frame: a fine inset line, doubled for VIP, with corner ornaments for VVIP. */
function Frame({ d, className }: { d: Design; className?: string }) {
    const color = alpha(d.rule, d.dark ? (d.key === 'ordinary' ? 0.12 : 0.4) : 0.25);

    return (
        <>
            <div aria-hidden className={cn('pointer-events-none absolute inset-2 rounded-[1.1rem] border', className)} style={{ borderColor: color }} />
            {d.key === 'vip' && <div aria-hidden className={cn('pointer-events-none absolute inset-3 rounded-[0.95rem] border', className)} style={{ borderColor: alpha(d.rule, 0.2) }} />}
            {d.key === 'vvip' &&
                (['left-3.5 top-3.5 border-l border-t', 'right-3.5 top-3.5 border-r border-t', 'bottom-3.5 left-3.5 border-b border-l', 'bottom-3.5 right-3.5 border-b border-r'] as const).map((pos) => (
                    <span key={pos} aria-hidden className={cn('pointer-events-none absolute h-3.5 w-3.5', pos)} style={{ borderColor: d.accent2 }} />
                ))}
        </>
    );
}

function TicketCard({ ticket: t, onOpen }: { ticket: T; onOpen: () => void }) {
    const d = t.design;
    const dimmed = isPast(t);

    return (
        <div className={cn('@container', dimmed && 'opacity-80')} style={passShadow}>
            <div className="flex flex-col @min-[40rem]:flex-row">
                {/* Main panel */}
                <button
                    onClick={onOpen}
                    aria-label={`Open ticket for ${t.event.title}`}
                    className={cn(
                        'group relative flex min-w-0 flex-1 flex-col overflow-hidden rounded-t-[1.4rem] text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7655F7] @min-[40rem]:min-h-[17rem] @min-[40rem]:rounded-l-[1.4rem] @min-[40rem]:rounded-tr-none',
                        MAIN_MASK,
                    )}
                    style={panelBackground(d)}
                >
                    <div aria-hidden className="absolute inset-x-0 top-0 z-20 h-[3px]" style={{ background: metal(d, 90) }} />
                    <Cover ticket={t} d={d} dimmed={dimmed} />
                    <Pattern d={d} />
                    <img aria-hidden src="/brand/tuvio-mark.png" alt="" className="pointer-events-none absolute -bottom-6 left-2 h-[85%] w-auto opacity-[0.045] grayscale" style={{ filter: d.dark ? 'brightness(10) grayscale(1)' : 'brightness(0) grayscale(1)' }} />
                    <Frame d={d} className="@max-[40rem]:rounded-b-none" />
                    {d.holo && <HoloSheen />}

                    <div className="relative z-10 flex flex-1 flex-col p-6 @max-[40rem]:pt-5 @min-[40rem]:p-7">
                        <div className="flex items-center justify-between gap-3">
                            <Brand d={d} />
                            <div className="flex items-center gap-1.5">
                                {t.event.category && (
                                    <span className="hidden rounded-full border px-2.5 py-[3px] text-[10px] font-extrabold uppercase tracking-[0.14em] @min-[30rem]:inline-flex" style={{ borderColor: d.accent, color: d.dark ? d.accent : d.ink }}>
                                        {t.event.category}
                                    </span>
                                )}
                                <StatusPill ticket={t} />
                            </div>
                        </div>

                        <div className="@max-[40rem]:mt-24">
                            <Eyebrow color={d.accent} className="mt-5 truncate">{t.event.organizer ?? 'TUVIO'}</Eyebrow>
                            <h3 className="mt-1.5 line-clamp-2 font-display text-[2.1rem] leading-[0.98] tracking-[-0.01em] @min-[40rem]:max-w-[70%] @min-[40rem]:text-[2.6rem]" style={{ color: d.ink }}>
                                {t.event.title}
                            </h3>
                            <div className="mt-3 h-[2px] w-10" style={{ background: metal(d, 90) }} />
                        </div>

                        <div className="mt-5 grid grid-cols-2 gap-x-5 gap-y-3 @min-[40rem]:mt-auto @min-[40rem]:grid-cols-4 @min-[40rem]:pt-5">
                            <Field label="Date" value={eventDate(t)} d={d} />
                            <Field label="Starts" value={eventTime(t)} d={d} />
                            <Field label="Venue" value={eventVenue(t)} d={d} />
                            <Field label="Location" value={eventLocation(t)} d={d} />
                        </div>
                        <div className="mt-4 grid grid-cols-2 gap-x-5 gap-y-3 border-t pt-3.5 @min-[40rem]:grid-cols-4" style={{ borderColor: alpha(d.rule, d.dark ? 0.14 : 0.18) }}>
                            <Field label="Ticket holder" value={t.attendee_name ?? 'Guest'} d={d} strong className="col-span-2 @min-[40rem]:col-span-1" />
                            <Field label="Category" value={t.type} d={d} />
                            <Field label="Price" value={ticketPrice(t)} d={d} />
                            <Field label="Ticket no." value={`Nº ${t.number}`} d={d} className="hidden @min-[40rem]:block" />
                        </div>
                    </div>
                </button>

                {/* Stub: category, privileges and the scannable QR */}
                <div
                    className={cn(
                        'relative flex items-center gap-4 overflow-hidden rounded-b-[1.4rem] p-5 @min-[40rem]:w-[14.5rem] @min-[40rem]:shrink-0 @min-[40rem]:flex-col @min-[40rem]:items-stretch @min-[40rem]:rounded-r-[1.4rem] @min-[40rem]:rounded-bl-none @min-[40rem]:p-6 @min-[40rem]:text-center',
                        STUB_MASK,
                    )}
                    style={stubBackground(d)}
                >
                    <div aria-hidden className="absolute inset-x-5 top-0 border-t-2 border-dotted @min-[40rem]:inset-x-auto @min-[40rem]:inset-y-5 @min-[40rem]:left-0 @min-[40rem]:border-l-2 @min-[40rem]:border-t-0" style={{ borderColor: alpha(d.rule, 0.45) }} />
                    <div aria-hidden className="absolute inset-x-0 top-0 hidden h-[3px] @min-[40rem]:block" style={{ background: metal(d, 90) }} />
                    {d.holo && <HoloSeal d={d} className="absolute right-4 top-4 hidden @min-[40rem]:grid" />}

                    <div className="min-w-0 flex-1 @min-[40rem]:flex-none">
                        <Eyebrow color={d.stubMuted}>{d.badge}</Eyebrow>
                        <p className={cn('mt-1.5 truncate font-black leading-none tracking-[0.02em]', d.label.length > 9 ? 'text-[1.2rem]' : d.label.length > 5 ? 'text-[1.5rem]' : 'text-[2rem]')}>
                            {!d.holo && <span className="mr-1 align-middle text-[0.5em]" style={{ color: d.accent }}>{d.glyph}</span>}
                            <span style={metalText(d)}>{d.label}</span>
                        </p>
                        <div className="mt-2.5 h-[2px] w-10 @min-[40rem]:mx-auto" style={{ background: metal(d, 90) }} />
                        <p className="mt-2.5 text-[11px] font-medium leading-snug" style={{ color: d.stubInk }}>
                            Admit one · {d.privilege}
                        </p>
                    </div>

                    <button
                        onClick={onOpen}
                        aria-label="Show QR code"
                        className="group/qr shrink-0 rounded-2xl bg-white p-2 text-center shadow-[0_10px_24px_-12px_rgb(0_0_0_/_0.6)] transition active:scale-95 @min-[40rem]:mt-auto @min-[40rem]:w-full @min-[40rem]:p-3"
                    >
                        <span className="mx-auto block w-fit transition-transform group-hover/qr:scale-105">
                            <Qr value={t.qr_token} size={88} />
                        </span>
                        <span className="mt-1 block font-mono text-[9px] font-bold tracking-[0.08em] text-[#0E1220] @min-[40rem]:text-[10px]">{t.code}</span>
                        <span className="mt-0.5 hidden items-center justify-center gap-1 text-[8.5px] font-extrabold uppercase tracking-[0.16em] text-[#1FB86A] @min-[40rem]:flex">
                            <ShieldCheck className="h-3 w-3" /> Scan at entry
                        </span>
                    </button>
                </div>
            </div>
        </div>
    );
}

function TicketPass({ ticket: t, onClose }: { ticket: T; onClose: () => void }) {
    const d = t.design;
    const used = t.status === 'used';

    return (
        <div style={passShadow} className="@container">
            <div className="relative overflow-hidden rounded-t-[1.75rem]" style={{ ...panelBackground(d), ...notchMask('bottom') }}>
                <div aria-hidden className="absolute inset-x-0 top-0 z-20 h-[3px]" style={{ background: metal(d, 90) }} />
                <Pattern d={d} />
                {d.holo && <HoloSheen />}

                <div className="relative h-44">
                    {t.event.cover_url && (
                        <img src={t.event.cover_url} alt="" className={cn('absolute inset-0 h-full w-full object-cover', isPast(t) && 'grayscale')} style={{ opacity: d.dark ? 0.85 : 0.6 }} />
                    )}
                    <div className="absolute inset-0" style={{ background: `linear-gradient(180deg, ${alpha(d.base, 0.15)} 0%, ${alpha(d.base, 0.4)} 55%, ${d.base} 100%)` }} />
                    <span className="absolute left-1/2 top-3 z-10 block h-1 w-10 -translate-x-1/2 rounded-full bg-white/50 sm:hidden" />
                    <div className="absolute inset-x-5 top-5 z-10 flex items-center justify-between gap-3">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-black/40 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-[0.16em] text-white backdrop-blur-md">
                            {d.glyph} {d.label}
                        </span>
                        <button onClick={onClose} aria-label="Close" className="grid h-10 w-10 place-items-center rounded-full bg-black/40 text-white backdrop-blur-md transition hover:bg-black/55">
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div className="relative px-6 pb-7">
                    <Brand d={d} />
                    <Eyebrow color={d.accent} className="mt-4">{t.event.organizer ?? 'TUVIO'}</Eyebrow>
                    <h2 className="mt-1.5 font-display text-[2.2rem] leading-[0.98]" style={{ color: d.ink }}>
                        {t.event.title}
                    </h2>
                    <div className="mt-3 h-[2px] w-10" style={{ background: metal(d, 90) }} />

                    <div className="mt-5 grid grid-cols-2 gap-x-4 gap-y-3.5">
                        <Field label="Date" value={eventDate(t)} d={d} />
                        <Field label="Starts" value={eventTime(t)} d={d} />
                        <Field label="Venue" value={eventVenue(t)} d={d} className="col-span-2" />
                        <Field label="Location" value={eventLocation(t)} d={d} className="col-span-2" />
                    </div>
                    <div className="mt-4 grid grid-cols-2 gap-x-4 gap-y-3.5 border-t pt-3.5" style={{ borderColor: alpha(d.rule, d.dark ? 0.14 : 0.18) }}>
                        <Field label="Ticket holder" value={t.attendee_name ?? 'Guest'} d={d} strong className="col-span-2" />
                        <Field label="Category" value={t.type} d={d} />
                        <Field label="Price" value={ticketPrice(t)} d={d} />
                    </div>

                    {t.perks.length > 0 && (
                        <div className="mt-5">
                            <Eyebrow color={d.muted}>Included with your ticket</Eyebrow>
                            <ul className="mt-2 flex flex-wrap gap-1.5">
                                {t.perks.map((perk) => (
                                    <li
                                        key={perk}
                                        className="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold"
                                        style={{ borderColor: alpha(d.accent, 0.4), color: d.ink, background: alpha(d.accent, 0.08) }}
                                    >
                                        <Check className="h-3 w-3" strokeWidth={3} style={{ color: d.accent }} /> {perk}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            {/* QR stub in the category colours */}
            <div className="relative overflow-hidden px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-6 text-center sm:rounded-b-[1.75rem]" style={{ ...stubBackground(d), ...notchMask('top') }}>
                <div aria-hidden className="absolute inset-x-5 top-0 border-t-2 border-dotted" style={{ borderColor: alpha(d.rule, 0.45) }} />

                <div className="flex items-center justify-center gap-2.5">
                    {d.holo && <HoloSeal d={d} />}
                    <div>
                        <Eyebrow color={d.stubMuted}>{d.badge}</Eyebrow>
                        <p className="mt-0.5 text-[1.6rem] font-black leading-none tracking-[0.02em]" style={metalText(d)}>
                            {d.label}
                        </p>
                    </div>
                </div>
                <p className="mt-2 text-xs" style={{ color: d.stubInk }}>
                    Admit one · {d.privilege}
                </p>

                <div className={cn('mx-auto mt-5 w-fit rounded-[1.5rem] bg-white p-4 shadow-[0_18px_40px_-20px_rgb(0_0_0_/_0.7)]', used && 'opacity-60')}>
                    <Qr value={t.qr_token} size={216} />
                    <p className="mt-2 font-mono text-sm font-bold tracking-[0.12em] text-[#0E1220]">{t.code}</p>
                    <p className="mt-1 flex items-center justify-center gap-1 text-[10px] font-extrabold uppercase tracking-[0.16em] text-[#1FB86A]">
                        <ShieldCheck className="h-3.5 w-3.5" /> TUVIO verified · Nº {t.number}
                    </p>
                </div>

                <StatusPill ticket={t} className="mt-4" />

                <p className="mx-auto mt-3 flex max-w-[17rem] items-start justify-center gap-2 text-xs leading-relaxed" style={{ color: d.stubMuted }}>
                    <ScanLine className="mt-px h-4 w-4 shrink-0" />
                    {used ? 'This ticket has already been checked in.' : 'Show this code at the entrance. Turn your screen brightness up for faster scanning.'}
                </p>

                <a
                    href={`/my-account/tickets/${t.code}/pdf`}
                    className="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full text-sm font-bold text-[#0B0B10] shadow-[0_14px_30px_-12px_rgb(0_0_0_/_0.7)] transition hover:brightness-110 active:scale-[0.98]"
                    style={{ background: metal(d, 100) }}
                >
                    <Download className="h-4 w-4" /> Download PDF ticket
                </a>
            </div>
        </div>
    );
}
