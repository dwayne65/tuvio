import { EventCard } from '@/Components/EventCard';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { tierMeta } from '@/lib/ticketTiers';
import { cn, formatDateTime, money } from '@/lib/utils';
import { EventSummary, PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { ArrowRight, CalendarDays, Check, ChevronLeft, ChevronsRight, Clock, Globe, MapPin, Minus, Plus, Share2, Ticket, Wallet } from 'lucide-react';
import { ReactNode, useMemo, useState } from 'react';

interface TicketTypeDTO {
    id: number;
    name: string;
    description: string | null;
    tier: string;
    perks: string[];
    price: { minor: number; currency: string; formatted: string };
    is_free: boolean;
    available: number | null;
    on_sale: boolean;
    max_per_order: number;
}

interface EventDetail extends EventSummary {
    description: string | null;
    end_at: string | null;
    venue_address: string | null;
    online_url: string | null;
    timezone: string;
    currency: string;
    refund_policy: string | null;
    schedule: { id: number; title: string; description: string | null; speaker: string | null; starts_at: string | null }[];
    ticket_types: TicketTypeDTO[];
}

export default function EventShow({ event, related }: PageProps<{ event: EventDetail; related: EventSummary[] }>) {
    const [qty, setQty] = useState<Record<number, number>>({});
    const [expanded, setExpanded] = useState(false);

    const total = useMemo(() => event.ticket_types.reduce((sum, t) => sum + (qty[t.id] ?? 0) * t.price.minor, 0), [qty, event.ticket_types]);
    const count = Object.values(qty).reduce((a, b) => a + b, 0);
    const start = event.start_at ? new Date(event.start_at) : null;
    const location = event.type === 'online' ? 'Online event' : [event.venue_name, event.venue_address, event.city].filter(Boolean).join(', ') || 'TBA';

    function setTicket(t: TicketTypeDTO, delta: number) {
        setQty((prev) => {
            const cap = t.available === null ? t.max_per_order : Math.min(t.max_per_order, t.available);
            const next = Math.max(0, Math.min(cap, (prev[t.id] ?? 0) + delta));
            return { ...prev, [t.id]: next };
        });
    }

    function checkout() {
        if (count === 0) {
            document.getElementById('tickets')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }
        const items = Object.entries(qty)
            .filter(([, q]) => q > 0)
            .map(([ticket_type_id, quantity]) => ({ ticket_type_id: Number(ticket_type_id), quantity }));
        router.post(`/events/${event.slug}/checkout`, { items });
    }

    function share() {
        if (navigator.share) {
            navigator.share({ title: event.title, url: window.location.href }).catch(() => {});
        } else {
            navigator.clipboard?.writeText(window.location.href);
        }
    }

    const cheapest = event.ticket_types.filter((t) => t.on_sale).sort((a, b) => a.price.minor - b.price.minor)[0];
    const totalLabel = count > 0 ? money(total, event.currency) : cheapest ? (cheapest.is_free ? 'Free' : cheapest.price.formatted) : '—';

    return (
        <PublicLayout immersive hideTabBar>
            <Head title={event.title} />

            {/* Hero */}
            <div className="relative md:mx-auto md:max-w-7xl md:px-6 md:pt-6 lg:px-8">
                <div className="relative h-[27rem] overflow-hidden rounded-b-[2.25rem] bg-card md:h-[30rem] md:rounded-[2.25rem] md:border md:border-tint/[0.08]">
                    {event.cover_image_path ? (
                        <img src={event.cover_image_path} alt="" className="h-full w-full object-cover" />
                    ) : (
                        <div
                            className="h-full w-full"
                            style={{ background: `radial-gradient(110% 80% at 75% 10%, ${event.category?.color ?? '#2f7dff'}, #0b0d14 70%)` }}
                        />
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-[#07080c] via-[#07080c]/40 to-[#07080c]/30" />

                    {/* Mobile top bar */}
                    <div className="absolute inset-x-4 top-[max(1rem,env(safe-area-inset-top))] flex items-center justify-between md:hidden">
                        <RoundGlassButton label="Back" onClick={() => (window.history.length > 1 ? window.history.back() : router.visit('/events'))}>
                            <ChevronLeft className="h-5 w-5" />
                        </RoundGlassButton>
                        <p className="max-w-[55%] truncate text-sm font-semibold text-white/90">{event.organization?.name ?? 'Event'}</p>
                        <RoundGlassButton label="Share" onClick={share}>
                            <Share2 className="h-[18px] w-[18px]" />
                        </RoundGlassButton>
                    </div>

                    <div className="absolute inset-x-5 bottom-6 md:inset-x-10 md:bottom-10">
                        <div className="flex flex-wrap items-center gap-2">
                            {event.category && <Badge tone="brand">{event.category.name}</Badge>}
                            <span className="rounded-full border border-white/15 bg-black/30 px-2.5 py-1 text-xs font-semibold capitalize text-white backdrop-blur-md">
                                {event.type}
                            </span>
                        </div>
                        <h1 className="mt-3 max-w-3xl font-display text-[3rem] leading-[0.92] text-white md:text-7xl">{event.title}</h1>
                    </div>

                    <button
                        onClick={share}
                        aria-label="Share"
                        className="absolute right-6 top-6 hidden h-11 w-11 place-items-center rounded-full border border-white/15 bg-black/30 text-white backdrop-blur-md hover:bg-black/50 md:grid"
                    >
                        <Share2 className="h-[18px] w-[18px]" />
                    </button>
                </div>
            </div>

            <div className="container-page pb-36 pt-5 md:pb-16 md:pt-8">
                <div className="grid gap-6 lg:grid-cols-[1fr_24rem] lg:gap-8">
                    <div className="min-w-0 space-y-6">
                        {/* Organizer */}
                        {event.organization && (
                            <div className="flex items-center gap-3">
                                <span className="grid h-12 w-12 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-brand-700 text-lg font-bold text-white ring-2 ring-white/10">
                                    {event.organization.name.charAt(0).toUpperCase()}
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate font-bold text-ink">{event.organization.name}</p>
                                    <p className="truncate text-sm text-slate-500">{event.type === 'online' ? 'Streaming online' : `Live from ${event.city ?? 'the venue'}`}</p>
                                </div>
                            </div>
                        )}

                        {/* Info tiles */}
                        <div className="grid grid-cols-3 gap-2.5">
                            <InfoTile icon={<Wallet className="h-4 w-4" />} label="Price" value={cheapest ? (cheapest.is_free ? 'Free' : cheapest.price.formatted) : '—'} />
                            <InfoTile icon={<CalendarDays className="h-4 w-4" />} label="Date" value={start ? start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) : 'TBA'} />
                            <InfoTile icon={<Clock className="h-4 w-4" />} label="Time" value={start ? start.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }) : 'TBA'} />
                            <div className="col-span-3 flex items-center gap-3 rounded-[1.25rem] border border-tint/[0.07] bg-card/80 p-3.5">
                                <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-tint/[0.06] text-brand-300">
                                    {event.type === 'online' ? <Globe className="h-4 w-4" /> : <MapPin className="h-4 w-4" />}
                                </span>
                                <div className="min-w-0">
                                    <p className="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Location</p>
                                    <p className="truncate text-sm font-semibold text-ink">{location}</p>
                                </div>
                            </div>
                        </div>

                        {event.description && (
                            <section>
                                <h2 className="text-lg font-bold text-ink">About the event</h2>
                                <p className={cn('mt-2 whitespace-pre-line text-[15px] leading-7 text-slate-500', !expanded && 'line-clamp-5')}>{event.description}</p>
                                {event.description.length > 280 && (
                                    <button onClick={() => setExpanded((v) => !v)} className="mt-1 text-sm font-semibold text-brand-300">
                                        {expanded ? 'Show less' : 'Read more'}
                                    </button>
                                )}
                            </section>
                        )}

                        {event.schedule.length > 0 && (
                            <section>
                                <h2 className="text-lg font-bold text-ink">Schedule</h2>
                                <ol className="relative mt-4 space-y-3 before:absolute before:bottom-3 before:left-[1.1rem] before:top-3 before:w-px before:bg-tint/10">
                                    {event.schedule.map((s) => (
                                        <li key={s.id} className="relative flex gap-4">
                                            <span className="relative z-10 mt-1 grid h-9 w-9 shrink-0 place-items-center rounded-full border border-tint/10 bg-card text-brand-300">
                                                <Clock className="h-4 w-4" />
                                            </span>
                                            <div className="flex-1 rounded-[1.25rem] border border-tint/[0.07] bg-card/80 p-4">
                                                <p className="text-xs font-semibold text-slate-400">{s.starts_at ? formatDateTime(s.starts_at) : ''}</p>
                                                <h3 className="mt-0.5 font-semibold text-ink">{s.title}</h3>
                                                {s.speaker && <p className="text-sm text-brand-300">{s.speaker}</p>}
                                                {s.description && <p className="mt-1 text-sm text-slate-500">{s.description}</p>}
                                            </div>
                                        </li>
                                    ))}
                                </ol>
                            </section>
                        )}

                        {event.refund_policy && (
                            <section className="rounded-[1.25rem] border border-tint/[0.07] bg-card/60 p-4">
                                <h2 className="text-sm font-bold text-ink">Refund policy</h2>
                                <p className="mt-1 text-sm text-slate-500">{event.refund_policy}</p>
                            </section>
                        )}
                    </div>

                    {/* Ticket picker */}
                    <aside id="tickets" className="scroll-mt-6">
                        <div className="rounded-[1.75rem] border border-tint/[0.08] bg-card/80 p-5 shadow-premium backdrop-blur-xl lg:sticky lg:top-24">
                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-brand-300">Get in the room</p>
                            <h2 className="mt-1 text-xl font-bold text-ink">Choose your pass</h2>

                            <div className="mt-4 space-y-2.5">
                                {event.ticket_types.length === 0 && <p className="text-sm text-slate-500">No tickets on sale yet.</p>}
                                {event.ticket_types.map((t) => {
                                    const selected = (qty[t.id] ?? 0) > 0;
                                    return (
                                        <div
                                            key={t.id}
                                            className={cn(
                                                'rounded-[1.25rem] border p-4 transition-colors',
                                                selected ? 'border-brand-500/70 bg-brand-500/10' : 'border-tint/[0.08] bg-tint/[0.03]',
                                            )}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <TierMark tier={t.tier} />
                                                        <p className="font-semibold text-ink">{t.name}</p>
                                                    </div>
                                                    {t.description && <p className="mt-0.5 text-xs text-slate-400">{t.description}</p>}
                                                    {t.perks.length > 0 && (
                                                        <ul className="mt-2 space-y-1">
                                                            {t.perks.map((perk) => (
                                                                <li key={perk} className="flex items-start gap-1.5 text-xs text-slate-500">
                                                                    <Check className="mt-px h-3.5 w-3.5 shrink-0 text-emerald-600" /> {perk}
                                                                </li>
                                                            ))}
                                                        </ul>
                                                    )}
                                                </div>
                                                <span className="shrink-0 font-bold text-ink">{t.is_free ? 'Free' : t.price.formatted}</span>
                                            </div>
                                            <div className="mt-3 flex items-center justify-between">
                                                {t.on_sale && t.available !== 0 ? (
                                                    <div className="flex items-center gap-1 rounded-full border border-tint/10 bg-tint/[0.04] p-1">
                                                        <QtyButton label={`Remove one ${t.name}`} onClick={() => setTicket(t, -1)} disabled={!qty[t.id]}>
                                                            <Minus className="h-4 w-4" />
                                                        </QtyButton>
                                                        <span className="w-7 text-center font-bold text-ink">{qty[t.id] ?? 0}</span>
                                                        <QtyButton label={`Add one ${t.name}`} onClick={() => setTicket(t, 1)} primary>
                                                            <Plus className="h-4 w-4" />
                                                        </QtyButton>
                                                    </div>
                                                ) : (
                                                    <Badge tone="neutral">{t.available === 0 ? 'Sold out' : 'Not on sale'}</Badge>
                                                )}
                                                {t.available !== null && t.available > 0 && t.available <= 10 && t.on_sale && (
                                                    <span className="text-xs font-semibold text-amber-700">Only {t.available} left</span>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="mt-5 hidden border-t border-tint/[0.08] pt-4 md:block">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-slate-500">{count > 0 ? `Total · ${count} ${count === 1 ? 'ticket' : 'tickets'}` : 'From'}</span>
                                    <span className="text-lg font-extrabold text-ink">{totalLabel}</span>
                                </div>
                                <Button onClick={checkout} className="mt-4 w-full" size="lg" disabled={event.ticket_types.length === 0}>
                                    {count > 0 ? 'Get tickets' : 'Select tickets'} <ArrowRight className="h-4 w-4" />
                                </Button>
                                <p className="mt-3 text-center text-xs text-slate-400">Tickets are emailed instantly · Mobile Money & cards</p>
                            </div>
                        </div>
                    </aside>
                </div>

                {related.length > 0 && (
                    <section className="mt-14">
                        <h2 className="text-[1.6rem] font-extrabold tracking-[-0.04em] text-ink">
                            You might <span className="font-display font-normal italic text-brand-300">also like</span>
                        </h2>
                        <div className="scroller -mx-4 mt-5 flex gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-6 sm:overflow-visible sm:px-0 lg:grid-cols-3">
                            {related.map((e) => (
                                <div key={e.id} className="w-[78vw] max-w-[21rem] shrink-0 sm:w-auto sm:max-w-none">
                                    <EventCard event={e} />
                                </div>
                            ))}
                        </div>
                    </section>
                )}
            </div>

            {/* Mobile sticky CTA */}
            <div className="fixed inset-x-0 bottom-0 z-50 px-4 pb-[max(0.9rem,env(safe-area-inset-bottom))] md:hidden">
                <div className="glass flex items-center gap-2 rounded-full p-1.5 shadow-premium">
                    <button
                        onClick={checkout}
                        disabled={event.ticket_types.length === 0}
                        className="flex h-[3.25rem] flex-1 items-center justify-center gap-2 rounded-full bg-brand-500 font-bold text-white glow-blue transition-transform active:scale-[0.98] disabled:opacity-50"
                    >
                        <Ticket className="h-5 w-5" />
                        {count > 0 ? `Get ${count} ${count === 1 ? 'ticket' : 'tickets'}` : 'Get a ticket'}
                    </button>
                    <div className="flex h-[3.25rem] items-center gap-1.5 pl-2 pr-4 text-right">
                        <div>
                            <p className="text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">{count > 0 ? 'Total' : 'From'}</p>
                            <p className="text-sm font-extrabold text-ink">{totalLabel}</p>
                        </div>
                        <ChevronsRight className="h-4 w-4 text-slate-400" />
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

function RoundGlassButton({ label, onClick, children }: { label: string; onClick: () => void; children: ReactNode }) {
    return (
        <button
            onClick={onClick}
            aria-label={label}
            className="grid h-11 w-11 place-items-center rounded-full border border-white/15 bg-black/30 text-white backdrop-blur-md active:scale-95"
        >
            {children}
        </button>
    );
}

function InfoTile({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
    return (
        <div className="min-w-0 rounded-[1.25rem] border border-tint/[0.07] bg-card/80 p-3.5">
            <span className="grid h-9 w-9 place-items-center rounded-full bg-tint/[0.06] text-brand-300">{icon}</span>
            <p className="mt-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">{label}</p>
            <p className="mt-0.5 truncate text-sm font-bold text-ink">{value}</p>
        </div>
    );
}

function QtyButton({ label, children, onClick, disabled, primary }: { label: string; children: ReactNode; onClick: () => void; disabled?: boolean; primary?: boolean }) {
    return (
        <button
            onClick={onClick}
            disabled={disabled}
            aria-label={label}
            className={cn(
                'grid h-8 w-8 place-items-center rounded-full transition-colors active:scale-90 disabled:opacity-30',
                primary ? 'bg-brand-500 text-white' : 'bg-tint/[0.08] text-slate-700 hover:bg-tint/[0.14]',
            )}
        >
            {children}
        </button>
    );
}

/** Small tier swatch (General / VIP / VVIP / Other) next to a ticket name. */
function TierMark({ tier }: { tier: string }) {
    const meta = tierMeta(tier);
    const Icon = meta.icon;

    return (
        <span className="grid h-6 w-6 shrink-0 place-items-center rounded-lg text-white" style={{ background: meta.swatch }} title={meta.label}>
            <Icon className="h-3.5 w-3.5" strokeWidth={2.4} />
        </span>
    );
}
