import { Qr } from '@/Components/Qr';
import { Button } from '@/Components/ui/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { cn, formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { CalendarDays, Check, Clock, Crown, Download, LucideIcon, MapPin, ScanLine, Star, Ticket as TicketIcon, User, Wallet, X } from 'lucide-react';
import { CSSProperties, ReactNode, useEffect, useState } from 'react';

interface T {
    code: string;
    qr_token: string;
    status: string;
    attendee_name: string | null;
    type: string;
    tier: string;
    perks: string[];
    price_minor: number;
    currency: string;
    used_at: string | null;
    event: { title: string; slug: string; cover_url: string | null; start_at: string | null; venue_name: string | null; city: string | null; type: string };
}

function isPast(t: T): boolean {
    return t.status !== 'issued' || (t.event.start_at !== null && new Date(t.event.start_at) < new Date());
}

/*
 | Ticket-only presentation. Passes are drawn as "physical" tickets in both
 | app themes, so their palettes are fixed here rather than taken from the
 | day/night tokens. The tier is read from the existing ticket type name.
*/
type Tier = 'standard' | 'vip' | 'vvip';

interface TierTheme {
    label: string;
    icon: LucideIcon;
    dark: boolean;
    panel: string;
    ink: string;
    muted: string;
    accent: string;
    iconTile: string;
    perforation: string;
    stub: CSSProperties;
    stubInk: string;
    stubMuted: string;
    tierText: string;
    bar: string;
    photoTint: string;
    fallback: string;
    button: string;
    buttonStyle: CSSProperties;
}

const HOLO = 'linear-gradient(100deg, #FF7AD9, #9D8CFF 45%, #5EE7FF)';
const GOLD = 'linear-gradient(100deg, #B8862F, #F3D58A 45%, #C9973B)';

const TIERS: Record<Tier, TierTheme> = {
    standard: {
        label: 'Standard',
        icon: TicketIcon,
        dark: false,
        panel: 'bg-white',
        ink: 'text-[#171C2B]',
        muted: 'text-[#6B7280]',
        accent: 'text-[#7655F7]',
        iconTile: 'bg-[#F0ECFF] text-[#7655F7]',
        perforation: 'border-white/35',
        stub: { background: 'linear-gradient(155deg, #8468FF 0%, #7655F7 35%, #4B32C3 100%)' },
        stubInk: 'text-white',
        stubMuted: 'text-white/70',
        tierText: 'text-white',
        bar: 'linear-gradient(180deg, #A994FF, #7655F7)',
        photoTint: 'linear-gradient(135deg, rgb(118 85 247 / 0.5), rgb(17 24 39 / 0.15) 60%)',
        fallback: 'radial-gradient(120% 110% at 90% 0%, rgb(118 85 247 / 0.7), #111827 62%)',
        button: 'text-white shadow-[0_14px_30px_-12px_rgb(118_85_247_/_0.9)]',
        buttonStyle: { background: 'linear-gradient(135deg, #7655F7, #5B3FD6)' },
    },
    vip: {
        label: 'VIP',
        icon: Star,
        dark: false,
        panel: 'bg-[#FBF7EE]',
        ink: 'text-[#1A1508]',
        muted: 'text-[#7A6A48]',
        accent: 'bg-clip-text text-transparent [background-image:linear-gradient(100deg,#9A6E1F,#D4A645_50%,#9A6E1F)]',
        iconTile: 'bg-[#1C1710] text-[#E9C46A]',
        perforation: 'border-[#E9C46A]/35',
        stub: { background: 'radial-gradient(120% 90% at 100% 0%, rgb(233 196 106 / 0.22), transparent 55%), linear-gradient(160deg, #221B10, #0B0A08)' },
        stubInk: 'text-[#F7EBD0]',
        stubMuted: 'text-[#CDB98C]/80',
        tierText: 'bg-clip-text text-transparent [background-image:linear-gradient(100deg,#B8862F,#F3D58A_45%,#C9973B)]',
        bar: GOLD,
        photoTint: 'linear-gradient(135deg, rgb(201 151 59 / 0.4), rgb(11 10 8 / 0.3) 60%)',
        fallback: 'radial-gradient(120% 110% at 90% 0%, rgb(233 196 106 / 0.45), #0B0A08 62%)',
        button: 'text-[#1A1508] shadow-[0_14px_30px_-12px_rgb(201_151_59_/_0.9)]',
        buttonStyle: { background: GOLD },
    },
    vvip: {
        label: 'VVIP',
        icon: Crown,
        dark: true,
        panel: 'bg-[#0C0C12]',
        ink: 'text-white',
        muted: 'text-[#A1A1B5]',
        accent: 'bg-clip-text text-transparent [background-image:linear-gradient(100deg,#FF7AD9,#9D8CFF_45%,#5EE7FF)]',
        iconTile: 'bg-white/[0.07] text-[#C6BCFF] ring-1 ring-inset ring-white/10',
        perforation: 'border-white/20',
        stub: {
            background:
                'radial-gradient(120% 80% at 100% 0%, rgb(157 140 255 / 0.4), transparent 60%), radial-gradient(100% 80% at 0% 100%, rgb(255 122 217 / 0.28), transparent 60%), #08080D',
        },
        stubInk: 'text-white',
        stubMuted: 'text-white/60',
        tierText: 'bg-clip-text text-transparent [background-image:linear-gradient(100deg,#FF7AD9,#9D8CFF_45%,#5EE7FF)]',
        bar: HOLO,
        photoTint: 'linear-gradient(135deg, rgb(255 122 217 / 0.35), rgb(94 231 255 / 0.15) 50%, rgb(8 8 13 / 0.35))',
        fallback: 'radial-gradient(110% 110% at 90% 0%, rgb(157 140 255 / 0.6), transparent 60%), radial-gradient(90% 90% at 0% 100%, rgb(255 122 217 / 0.45), #08080D 70%)',
        button: 'text-[#08080D] shadow-[0_14px_34px_-12px_rgb(157_140_255_/_0.9)]',
        buttonStyle: { background: HOLO },
    },
};

/** The design for the ticket's tier (General and Other share the standard look). */
function ticketTier(t: T): TierTheme {
    if (t.tier === 'vvip') {
        return TIERS.vvip;
    }

    return t.tier === 'vip' ? TIERS.vip : TIERS.standard;
}

/** Big stub label: the tier name, or the organizer's own name for "Other" tickets. */
function tierLabel(t: T): string {
    if (t.tier === 'other') {
        return t.type;
    }

    return t.tier === 'general' ? 'General' : ticketTier(t).label;
}

function ticketPrice(t: T): string {
    return t.price_minor === 0 ? 'Free' : money(t.price_minor, t.currency);
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

const passShadow: CSSProperties = { filter: 'drop-shadow(0 1px 1px rgb(17 24 39 / 0.08)) drop-shadow(0 26px 34px rgb(17 24 39 / 0.18))' };

interface StatusStyle {
    label: string;
    className: string;
    dot: string;
}

/** Maps the ticket's existing status (plus whether the event has passed) to a display style. */
function statusStyle(t: T): StatusStyle {
    switch (t.status) {
        case 'used':
            return { label: 'Checked in', className: 'bg-[#F0ECFF] text-[#5B3FD6]', dot: 'bg-[#7655F7]' };
        case 'refunded':
            return { label: 'Refunded', className: 'bg-amber-100 text-amber-800', dot: 'bg-amber-500' };
        case 'void':
            return { label: 'Cancelled', className: 'bg-rose-100 text-rose-700', dot: 'bg-rose-500' };
        case 'issued':
            return isPast(t)
                ? { label: 'Expired', className: 'bg-[#EEF0F4] text-[#475569]', dot: 'bg-[#94a3b8]' }
                : { label: 'Valid', className: 'bg-emerald-100 text-emerald-800', dot: 'bg-emerald-500' };
        default:
            return { label: t.status.charAt(0).toUpperCase() + t.status.slice(1), className: 'bg-[#EEF0F4] text-[#475569]', dot: 'bg-[#94a3b8]' };
    }
}

function eventDate(t: T): string {
    return t.event.start_at ? formatDate(t.event.start_at, { weekday: 'short' }) : 'TBA';
}

function eventTime(t: T): string {
    return t.event.start_at ? new Date(t.event.start_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : 'TBA';
}

function eventVenue(t: T): string {
    return t.event.type === 'online' ? 'Online event' : [t.event.venue_name, t.event.city].filter(Boolean).join(', ') || 'TBA';
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
                    <Link href="/my-account/orders" className="text-sm font-semibold text-slate-500 hover:text-ink">
                        Orders
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
                    <div className="mt-6 grid max-w-4xl gap-7">
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
        <span className={cn('inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold tracking-wide', status.className, className)}>
            <span className={cn('h-1.5 w-1.5 rounded-full', status.dot)} />
            {status.label}
        </span>
    );
}

function Wordmark({ tier, className }: { tier: TierTheme; className?: string }) {
    return (
        <span className={cn('inline-flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-[0.22em]', className)}>
            <span className="grid h-5 w-5 place-items-center rounded-md text-white" style={{ background: tier.bar }}>
                <TicketIcon className={cn('h-3 w-3', tier.dark || tier === TIERS.vip ? 'text-[#08080D]' : 'text-white')} strokeWidth={2.6} />
            </span>
            TicketFlow
        </span>
    );
}

/** Event title split like a print ticket: first word heavy and in the tier accent, the rest light. */
function SplitTitle({ title, tier, className }: { title: string; tier: TierTheme; className?: string }) {
    const [first, ...rest] = title.trim().split(/\s+/);

    return (
        <h3 className={cn('line-clamp-2 break-words uppercase leading-[1.02] tracking-[-0.02em]', className)}>
            <span className={cn('font-extrabold', tier.accent)}>{first}</span>
            {rest.length > 0 && <span className={cn('font-normal', tier.ink)}> {rest.join(' ')}</span>}
        </h3>
    );
}

/** The event cover (or a tier-coloured fallback) washed in the tier tint. */
function Photo({ ticket: t, tier, dimmed, className }: { ticket: T; tier: TierTheme; dimmed: boolean; className?: string }) {
    return (
        <div className={cn('overflow-hidden', className)} style={{ background: '#0B0B10' }}>
            {t.event.cover_url ? (
                <img
                    src={t.event.cover_url}
                    alt=""
                    className={cn('h-full w-full object-cover transition-transform duration-700 group-hover:scale-105', dimmed && 'grayscale')}
                />
            ) : (
                <div className="h-full w-full" style={{ background: tier.fallback }} />
            )}
            <div className="absolute inset-0" style={{ background: tier.photoTint }} />
        </div>
    );
}

/** Slow light sweep across VVIP passes (skipped when the visitor prefers reduced motion). */
function HoloSheen() {
    const reduceMotion = useReducedMotion();

    if (reduceMotion) {
        return null;
    }

    return (
        <motion.div
            aria-hidden
            className="pointer-events-none absolute inset-y-0 z-30 w-1/3 -skew-x-12"
            style={{ background: 'linear-gradient(90deg, transparent, rgb(255 255 255 / 0.09), transparent)' }}
            initial={{ left: '-40%' }}
            animate={{ left: '140%' }}
            transition={{ duration: 3.2, repeat: Infinity, repeatDelay: 2.8, ease: 'easeInOut' }}
        />
    );
}

function TicketCard({ ticket: t, onOpen }: { ticket: T; onOpen: () => void }) {
    const tier = ticketTier(t);
    const dimmed = isPast(t);
    const TierIcon = tier.icon;

    return (
        <div className={cn('@container', dimmed && 'opacity-80')} style={passShadow}>
            <div className="flex flex-col @min-[40rem]:flex-row">
                {/* Main: info panel + diagonal photo */}
                <button
                    onClick={onOpen}
                    aria-label={`Open ticket for ${t.event.title}`}
                    className={cn(
                        'group relative flex min-w-0 flex-1 flex-col overflow-hidden rounded-t-[1.4rem] text-left focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#7655F7] @min-[40rem]:min-h-[15.5rem] @min-[40rem]:flex-row @min-[40rem]:rounded-l-[1.4rem] @min-[40rem]:rounded-tr-none',
                        tier.panel,
                        MAIN_MASK,
                    )}
                >
                    {tier === TIERS.vvip && <div className="absolute inset-x-0 top-0 z-20 h-1" style={{ background: HOLO }} />}
                    {tier === TIERS.vvip && <HoloSheen />}

                    <Photo
                        ticket={t}
                        tier={tier}
                        dimmed={dimmed}
                        className="relative order-first h-40 [clip-path:polygon(0_0,100%_0,100%_74%,0_100%)] @min-[40rem]:absolute @min-[40rem]:inset-y-0 @min-[40rem]:right-0 @min-[40rem]:h-full @min-[40rem]:w-[48%] @min-[40rem]:[clip-path:polygon(30%_0,100%_0,100%_100%,0_100%)]"
                    />

                    {/* Slanted accent bars along the photo edge */}
                    <span
                        aria-hidden
                        className="absolute left-[calc(52%+8%)] top-0 z-10 hidden h-[46%] w-5 -skew-x-[25deg] rounded-b-full opacity-90 @min-[40rem]:block"
                        style={{ background: tier.bar }}
                    />
                    <span aria-hidden className="absolute bottom-0 left-[calc(52%-1.1rem)] z-10 hidden h-[38%] w-px -skew-x-[25deg] @min-[40rem]:block" style={{ background: tier.bar }} />
                    <span aria-hidden className="absolute bottom-0 left-[calc(52%-1.6rem)] z-10 hidden h-[26%] w-px -skew-x-[25deg] @min-[40rem]:block" style={{ background: tier.bar }} />

                    <div className="relative z-10 flex flex-1 flex-col p-5 pt-3 @min-[40rem]:w-[54%] @min-[40rem]:flex-none @min-[40rem]:p-7">
                        <div className="flex items-center justify-between gap-3">
                            <Wordmark tier={tier} className={tier.muted} />
                            <StatusPill ticket={t} className="@min-[40rem]:hidden" />
                        </div>

                        <SplitTitle title={t.event.title} tier={tier} className="mt-4 text-[1.65rem] @min-[40rem]:text-[1.9rem]" />

                        <div className="mt-4 @min-[40rem]:mt-auto @min-[40rem]:pt-5">
                            <p className={cn('text-[1.55rem] font-bold leading-none tracking-[-0.02em] tabular-nums', tier.ink)}>{eventDate(t)}</p>
                            <p className={cn('mt-1.5 text-xs font-semibold uppercase tracking-[0.14em]', tier.muted)}>Starts {eventTime(t)}</p>
                            <p className={cn('mt-3 flex items-start gap-1.5 text-sm font-medium', tier.ink)}>
                                <MapPin className={cn('mt-0.5 h-4 w-4 shrink-0', tier.muted)} />
                                <span className="line-clamp-1">{eventVenue(t)}</span>
                            </p>
                        </div>
                    </div>
                </button>

                {/* Stub: tier, attendee, scannable QR */}
                <div
                    className={cn(
                        'relative flex items-center gap-4 overflow-hidden rounded-b-[1.4rem] p-5 @min-[40rem]:w-[13rem] @min-[40rem]:shrink-0 @min-[40rem]:flex-col @min-[40rem]:items-stretch @min-[40rem]:rounded-r-[1.4rem] @min-[40rem]:rounded-bl-none @min-[40rem]:p-6',
                        STUB_MASK,
                    )}
                    style={tier.stub}
                >
                    <div aria-hidden className={cn('absolute inset-x-5 top-0 border-t-2 border-dashed @min-[40rem]:inset-x-auto @min-[40rem]:inset-y-5 @min-[40rem]:left-0 @min-[40rem]:border-l-2 @min-[40rem]:border-t-0', tier.perforation)} />
                    {tier !== TIERS.standard && <div aria-hidden className="pointer-events-none absolute inset-2 rounded-[1rem] ring-1 ring-inset ring-white/10" />}

                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <TierIcon className={cn('h-4 w-4', tier === TIERS.vip ? 'text-[#E9C46A]' : tier === TIERS.vvip ? 'text-[#C6BCFF]' : 'text-white/85')} strokeWidth={2.4} />
                            <span className={cn('truncate text-[1.6rem] font-black leading-none tracking-[-0.02em]', tierLabel(t).length > 8 && 'text-[1.15rem]', tier.tierText)}>{tierLabel(t)}</span>
                        </div>
                        <p className={cn('mt-1.5 truncate text-xs font-semibold', tier.stubMuted)}>{t.type}</p>
                        <p className={cn('mt-2 text-[10px] font-bold uppercase tracking-[0.16em]', tier.stubMuted)}>Ticket price</p>
                        <p className={cn('text-lg font-extrabold leading-tight tabular-nums', tier.stubInk)}>{ticketPrice(t)}</p>
                        <p className={cn('mt-3 text-[10px] font-bold uppercase tracking-[0.16em]', tier.stubMuted)}>Admit one</p>
                        <p className={cn('truncate text-sm font-semibold', tier.stubInk)}>{t.attendee_name ?? 'Guest'}</p>
                        <StatusPill ticket={t} className="mt-3 hidden @min-[40rem]:inline-flex" />
                    </div>

                    <button
                        onClick={onOpen}
                        aria-label="Show QR code"
                        className="group/qr shrink-0 rounded-2xl bg-white p-2 text-center shadow-[0_10px_24px_-12px_rgb(0_0_0_/_0.6)] transition active:scale-95 @min-[40rem]:mt-auto @min-[40rem]:w-full @min-[40rem]:p-2.5"
                    >
                        <span className="mx-auto block w-fit transition-transform group-hover/qr:scale-105">
                            <Qr value={t.qr_token} size={72} />
                        </span>
                        <span className="mt-1 block font-mono text-[9px] font-semibold tracking-[0.06em] text-[#171C2B] @min-[40rem]:text-[10px]">{t.code}</span>
                    </button>
                </div>
            </div>
        </div>
    );
}

function Detail({ icon: Icon, label, value, tier, className }: { icon: LucideIcon; label: string; value: string; tier: TierTheme; className?: string }) {
    return (
        <div className={cn('flex min-w-0 items-start gap-3', className)}>
            <span className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-xl', tier.iconTile)}>
                <Icon className="h-4 w-4" strokeWidth={2.2} />
            </span>
            <div className="min-w-0">
                <p className={cn('text-[10px] font-bold uppercase tracking-[0.14em]', tier.muted)}>{label}</p>
                <p className={cn('mt-0.5 break-words text-sm font-semibold leading-snug', tier.ink)}>{value}</p>
            </div>
        </div>
    );
}

function TicketPass({ ticket: t, onClose }: { ticket: T; onClose: () => void }) {
    const tier = ticketTier(t);
    const used = t.status === 'used';
    const TierIcon = tier.icon;

    return (
        <div style={passShadow}>
            <div className={cn('relative overflow-hidden rounded-t-[1.75rem]', tier.panel)} style={notchMask('bottom')}>
                {tier === TIERS.vvip && <div className="absolute inset-x-0 top-0 z-20 h-1" style={{ background: HOLO }} />}
                {tier === TIERS.vvip && <HoloSheen />}

                <div className="relative h-44">
                    <Photo ticket={t} tier={tier} dimmed={isPast(t)} className="absolute inset-0 [clip-path:polygon(0_0,100%_0,100%_72%,0_100%)]" />
                    <span className="absolute left-1/2 top-3 z-10 block h-1 w-10 -translate-x-1/2 rounded-full bg-white/50 sm:hidden" />
                    <div className="absolute inset-x-5 top-5 z-10 flex items-center justify-between gap-3">
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-black/35 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-[0.16em] text-white backdrop-blur-md">
                            <TierIcon className="h-3.5 w-3.5" strokeWidth={2.4} />
                            {tierLabel(t)}
                        </span>
                        <button
                            onClick={onClose}
                            aria-label="Close"
                            className="grid h-10 w-10 place-items-center rounded-full bg-black/35 text-white backdrop-blur-md transition hover:bg-black/50"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div className="relative px-6 pb-7">
                    <Wordmark tier={tier} className={tier.muted} />
                    <SplitTitle title={t.event.title} tier={tier} className="mt-3 text-[1.85rem]" />
                    <p className={cn('mt-2 text-xs font-semibold uppercase tracking-[0.14em]', tier.muted)}>{t.type}</p>

                    <div className="mt-6 grid grid-cols-2 gap-x-4 gap-y-4">
                        <Detail icon={User} label="Attendee" value={t.attendee_name ?? 'Guest'} tier={tier} />
                        <Detail icon={Wallet} label="Price" value={ticketPrice(t)} tier={tier} />
                        <Detail icon={CalendarDays} label="Date" value={eventDate(t)} tier={tier} />
                        <Detail icon={Clock} label="Time" value={eventTime(t)} tier={tier} />
                        <Detail icon={MapPin} label="Venue" value={eventVenue(t)} tier={tier} className="col-span-2" />
                    </div>

                    {t.perks.length > 0 && (
                        <div className="mt-6">
                            <p className={cn('text-[10px] font-bold uppercase tracking-[0.14em]', tier.muted)}>Included with your ticket</p>
                            <ul className="mt-2.5 flex flex-wrap gap-1.5">
                                {t.perks.map((perk) => (
                                    <li key={perk} className={cn('inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold', tier.iconTile)}>
                                        <Check className="h-3 w-3" strokeWidth={3} /> {perk}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            {/* QR stub in the tier colours */}
            <div
                className="relative overflow-hidden px-6 pb-[max(1.5rem,env(safe-area-inset-bottom))] pt-7 text-center sm:rounded-b-[1.75rem]"
                style={{ ...tier.stub, ...notchMask('top') }}
            >
                <div aria-hidden className={cn('absolute inset-x-5 top-0 border-t-2 border-dashed', tier.perforation)} />

                <div className={cn('mx-auto w-fit rounded-[1.5rem] bg-white p-4 shadow-[0_18px_40px_-20px_rgb(0_0_0_/_0.7)]', used && 'opacity-60')}>
                    <Qr value={t.qr_token} size={210} />
                </div>

                <div className="mt-5 flex items-center justify-center gap-2">
                    <span className={cn('text-[10px] font-bold uppercase tracking-[0.14em]', tier.stubMuted)}>Ref</span>
                    <span className={cn('font-mono text-sm font-semibold tracking-[0.14em]', tier.stubInk)}>{t.code}</span>
                </div>
                <StatusPill ticket={t} className="mt-3" />

                <p className={cn('mx-auto mt-4 flex max-w-[16rem] items-start justify-center gap-2 text-xs leading-relaxed', tier.stubMuted)}>
                    <ScanLine className="mt-px h-4 w-4 shrink-0" />
                    {used ? 'This ticket has already been checked in.' : 'Show this code at the entrance. Turn your screen brightness up for faster scanning.'}
                </p>

                <a
                    href={`/my-account/tickets/${t.code}/pdf`}
                    className={cn('mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-full text-sm font-bold transition hover:brightness-110 active:scale-[0.98]', tier.button)}
                    style={tier.buttonStyle}
                >
                    <Download className="h-4 w-4" /> Download PDF
                </a>
            </div>
        </div>
    );
}
