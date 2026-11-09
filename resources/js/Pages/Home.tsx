import { categoryIcon } from '@/Components/CategoryIcon';
import { EventCard } from '@/Components/EventCard';
import { TiltCard } from '@/Components/motion/TiltCard';
import { Button } from '@/Components/ui/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { fadeUp } from '@/lib/motion';
import { cn, money } from '@/lib/utils';
import { Category, EventSummary, PageProps, Plan } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ArrowRight, ArrowUpRight, Check, QrCode, Search, ShieldCheck, SlidersHorizontal, Wallet } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

type Props = {
    categories: Category[];
    featuredEvents: EventSummary[];
    upcomingEvents: EventSummary[];
    plans: Plan[];
    stats: { events: number; tickets: number; organizers: number };
};

export default function Home({ auth, categories, featuredEvents, upcomingEvents, plans, stats }: PageProps<Props>) {
    const [q, setQ] = useState('');
    const firstName = auth.user?.name.split(' ')[0];
    const spotlight = featuredEvents[0] ?? upcomingEvents[0];

    function search(e: FormEvent) {
        e.preventDefault();
        router.get('/events', { q });
    }

    return (
        <PublicLayout>
            <Head title="Discover Events. Create Experiences. Sell Tickets." />

            {/* Hero */}
            <section className="relative">
                {/* Soft glow that fades out fully inside the section, so there is no hard edge */}
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-0 bg-[radial-gradient(70%_55%_at_95%_0%,rgb(47_125_255/0.22),transparent_70%),radial-gradient(50%_45%_at_0%_40%,rgb(37_101_224/0.12),transparent_70%)]"
                />

                <div className="container-page relative grid items-center gap-12 pb-8 pt-3 md:py-20 lg:grid-cols-[1.1fr_0.9fr]">
                    <motion.div initial="hidden" animate="show" variants={{ hidden: {}, show: { transition: { staggerChildren: 0.07 } } }}>
                        <motion.p variants={fadeUp} className="text-[15px] font-medium text-slate-500">
                            Hi {firstName ?? 'friend'}{' '}
                            <span className="inline-block origin-[70%_70%] animate-[wave_2.4s_ease-in-out_infinite]">👋</span>
                        </motion.p>
                        <motion.h1
                            variants={fadeUp}
                            className="mt-2 text-[2.15rem] font-extrabold leading-[1.05] tracking-[-0.045em] text-ink sm:text-5xl md:text-6xl"
                        >
                            We’re thrilled to get you back{' '}
                            <span className="font-display font-normal italic tracking-normal text-gradient">out there.</span>
                        </motion.h1>
                        <motion.p variants={fadeUp} className="mt-4 hidden max-w-xl text-lg leading-8 text-slate-500 md:block">
                            Concerts, conferences, festivals and nights you’ll talk about for years. Find your next one, or host it — secure QR tickets, Mobile Money and cards.
                        </motion.p>

                        {/* Mode pills */}
                        <motion.div variants={fadeUp} className="mt-6 inline-flex rounded-full border border-tint/[0.08] bg-tint/[0.03] p-1">
                            <ModePill href="/events" active>Buy</ModePill>
                            <ModePill href={auth.user ? '/dashboard' : '/register'}>Sell</ModePill>
                            <ModePill href={auth.user ? '/dashboard/events/create' : '/register'}>Create</ModePill>
                        </motion.div>

                        {/* Search */}
                        <motion.form variants={fadeUp} onSubmit={search} className="mt-5 flex max-w-xl items-center gap-2.5">
                            <label className="relative flex-1">
                                <span className="sr-only">Search events</span>
                                <Search className="pointer-events-none absolute left-4 top-1/2 h-[18px] w-[18px] -translate-y-1/2 text-slate-400" />
                                <input
                                    value={q}
                                    onChange={(e) => setQ(e.target.value)}
                                    placeholder="Search events, artists, venues"
                                    className="h-14 w-full rounded-full border border-tint/10 bg-tint/[0.05] pl-11 pr-4 text-[15px] text-ink placeholder:text-slate-400 focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/15"
                                />
                            </label>
                            <button
                                type="submit"
                                aria-label="Search"
                                className="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-ink text-canvas transition-transform active:scale-95"
                            >
                                <SlidersHorizontal className="h-5 w-5" />
                            </button>
                        </motion.form>

                        <motion.div variants={fadeUp} className="mt-8 hidden gap-10 md:flex">
                            <Stat value={stats.events} label="Live events" />
                            <Stat value={stats.tickets} label="Tickets issued" />
                            <Stat value={stats.organizers} label="Organizers" />
                        </motion.div>
                    </motion.div>

                    {/* Desktop spotlight pass */}
                    {spotlight && (
                        <TiltCard className="relative mx-auto hidden w-full max-w-[25rem] lg:block" max={7}>
                            <div className="float-y">
                                <SpotlightPass event={spotlight} />
                            </div>
                        </TiltCard>
                    )}
                </div>
            </section>

            {/* Category chips */}
            <section className="container-page">
                <div className="scroller -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0">
                    <Chip href="/events" active>All events</Chip>
                    {categories.map((c) => {
                        const Icon = categoryIcon(c.icon);
                        return (
                            <Chip key={c.id} href={`/events?category=${c.slug}`}>
                                <Icon className="h-4 w-4" style={{ color: c.color ?? undefined }} /> {c.name}
                            </Chip>
                        );
                    })}
                </div>
            </section>

            {/* Featured: swipeable on phones, grid on desktop */}
            {featuredEvents.length > 0 && (
                <section className="container-page pt-8 md:pt-14">
                    <SectionHead eyebrow="Hand-picked" title="Featured events" action={{ label: 'See all', href: '/events' }} />
                    <div className="scroller -mx-4 mt-5 flex gap-4 overflow-x-auto px-4 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-6 sm:overflow-visible sm:px-0 lg:grid-cols-3">
                        {featuredEvents.map((e) => (
                            <div key={e.id} className="w-[80vw] max-w-[22rem] shrink-0 sm:w-auto sm:max-w-none">
                                <EventCard event={e} />
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {/* Upcoming: compact rows */}
            {upcomingEvents.length > 0 && (
                <section className="container-page pt-10 md:pt-16">
                    <SectionHead eyebrow="Don’t miss out" title="Coming soon" action={{ label: 'See all', href: '/events?sort=soon' }} />
                    <div className="mt-5 grid gap-3 md:grid-cols-2 md:gap-4">
                        {upcomingEvents.map((e) => (
                            <EventCard key={e.id} event={e} compact />
                        ))}
                    </div>
                </section>
            )}

            {/* How it works */}
            <section id="how-it-works" className="container-page scroll-mt-24 pt-14 md:pt-24">
                <SectionHead eyebrow="From idea to sold-out" title="How it works" />
                <div className="mt-6 grid gap-3 md:grid-cols-3 md:gap-5">
                    <Step n="01" icon={<Wallet className="h-5 w-5" />} title="Create & publish" body="Build your event with multiple ticket types and publish a beautiful page in minutes." />
                    <Step n="02" icon={<ShieldCheck className="h-5 w-5" />} title="Sell securely" body="Mobile Money and cards. Every payment is verified server-side before a ticket is issued." />
                    <Step n="03" icon={<QrCode className="h-5 w-5" />} title="Scan & check in" body="Attendees get QR tickets by email. Scan at the gate — duplicate entry is impossible." />
                </div>
            </section>

            {/* Organizer CTA */}
            <section className="container-page pt-14 md:pt-24">
                <div className="relative overflow-hidden rounded-[2rem] border border-white/10 bg-gradient-to-br from-brand-500 via-brand-700 to-[#0b1a44] px-6 py-10 md:px-16 md:py-16">
                    <div className="absolute -right-16 -top-20 h-72 w-72 rounded-full bg-white/15 blur-3xl" />
                    <div className="absolute inset-0 dot-grid opacity-40" />
                    <div className="relative max-w-2xl">
                        <h2 className="text-3xl font-extrabold leading-tight tracking-[-0.04em] text-white md:text-5xl">
                            Your crowd is <span className="font-display font-normal italic">waiting.</span>
                        </h2>
                        <p className="mt-4 text-[15px] leading-7 text-white/75 md:text-lg">
                            Real-time dashboards, gate scanning, printable tickets and fast payouts — everything an organizer needs.
                        </p>
                        <div className="mt-7 flex flex-wrap gap-3">
                            <Button href="/register" variant="secondary" size="lg" className="bg-white text-[#0b0d12]">
                                Start selling <ArrowRight className="h-4 w-4" />
                            </Button>
                            <Button href="/pricing" variant="outline" size="lg" className="border-white/30 bg-white/10 text-white">
                                See pricing
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            {/* Pricing */}
            {plans.length > 0 && (
                <section className="container-page pt-14 md:pt-24">
                    <SectionHead eyebrow="Plans for every organizer" title="Simple, transparent pricing" />
                    <div className="scroller -mx-4 mt-6 flex gap-4 overflow-x-auto px-4 pb-2 lg:mx-0 lg:grid lg:grid-cols-3 lg:gap-6 lg:overflow-visible lg:px-0">
                        {plans.map((plan, i) => (
                            <div
                                key={plan.id}
                                className={cn(
                                    'relative flex w-[82vw] max-w-sm shrink-0 flex-col rounded-[1.75rem] border p-7 lg:w-auto lg:max-w-none',
                                    i === 1 ? 'border-brand-500/60 bg-gradient-to-b from-brand-500/15 to-card' : 'border-tint/[0.08] bg-card/80',
                                )}
                            >
                                {i === 1 && (
                                    <span className="absolute right-6 top-6 rounded-full bg-brand-500 px-3 py-1 text-[11px] font-bold text-white">Popular</span>
                                )}
                                <h3 className="text-lg font-bold text-ink">{plan.name}</h3>
                                <div className="mt-4 flex items-baseline gap-1">
                                    <span className="text-4xl font-extrabold tracking-tight text-ink">
                                        {plan.price.minor === 0 ? 'Free' : money(plan.price.minor, plan.price.currency)}
                                    </span>
                                    {plan.price.minor > 0 && <span className="text-sm text-slate-500">/{plan.interval}</span>}
                                </div>
                                <ul className="mt-6 flex-1 space-y-3">
                                    {plan.features.map((f) => (
                                        <li key={f} className="flex items-start gap-2.5 text-sm text-slate-600">
                                            <span className="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full bg-brand-500/20">
                                                <Check className="h-3 w-3 text-brand-300" />
                                            </span>
                                            {f}
                                        </li>
                                    ))}
                                </ul>
                                <Button href="/register" variant={i === 1 ? 'primary' : 'outline'} className="mt-8 w-full">
                                    Get started
                                </Button>
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {/* Newsletter */}
            <section className="container-page pb-6 pt-14 md:pb-16 md:pt-24">
                <div className="flex flex-col items-start gap-6 rounded-[2rem] border border-tint/[0.08] bg-card/80 p-7 md:flex-row md:items-center md:justify-between md:p-10">
                    <div>
                        <h3 className="text-2xl font-extrabold tracking-tight text-ink">
                            Never miss <span className="font-display font-normal italic text-brand-300">a night out</span>
                        </h3>
                        <p className="mt-1.5 text-slate-500">The best events, delivered to your inbox weekly.</p>
                    </div>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            const email = (e.currentTarget.elements.namedItem('email') as HTMLInputElement).value;
                            router.post('/newsletter', { email }, { preserveScroll: true });
                        }}
                        className="flex w-full items-center gap-2 rounded-full border border-tint/10 bg-tint/[0.04] p-1.5 md:max-w-md"
                    >
                        <input
                            name="email"
                            type="email"
                            required
                            placeholder="you@email.com"
                            aria-label="Email address"
                            className="h-11 min-w-0 flex-1 bg-transparent pl-4 text-[15px] text-ink placeholder:text-slate-400 focus:outline-none"
                        />
                        <Button type="submit">Subscribe</Button>
                    </form>
                </div>
            </section>
        </PublicLayout>
    );
}

function ModePill({ href, active, children }: { href: string; active?: boolean; children: ReactNode }) {
    return (
        <Link
            href={href}
            className={cn(
                'rounded-full px-5 py-2 text-sm font-semibold transition-colors',
                active ? 'bg-brand-500 text-white glow-blue' : 'text-slate-500 hover:text-ink',
            )}
        >
            {children}
        </Link>
    );
}

function Chip({ href, active, children }: { href: string; active?: boolean; children: ReactNode }) {
    return (
        <Link
            href={href}
            className={cn(
                'inline-flex h-10 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition-colors',
                active
                    ? 'border-brand-500 bg-brand-500 text-white'
                    : 'border-tint/[0.08] bg-tint/[0.04] text-slate-600 hover:border-tint/20 hover:text-ink',
            )}
        >
            {children}
        </Link>
    );
}

function SpotlightPass({ event }: { event: EventSummary }) {
    return (
        <Link href={`/events/${event.slug}`} className="block overflow-hidden rounded-[2rem] border border-tint/10 bg-card shadow-premium">
            <div className="relative aspect-[4/3.6] overflow-hidden">
                {event.cover_image_path ? (
                    <img src={event.cover_image_path} alt="" className="h-full w-full object-cover" />
                ) : (
                    <div className="h-full w-full" style={{ background: `radial-gradient(120% 90% at 80% 0%, ${event.category?.color ?? '#2f7dff'}, #0b0d14 70%)` }} />
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/20 to-transparent" />
                <span className="absolute left-5 top-5 rounded-full border border-white/15 bg-black/30 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em] text-white backdrop-blur-md">
                    Spotlight
                </span>
                <h2 className="absolute inset-x-6 bottom-4 line-clamp-2 font-display text-[2.6rem] leading-[0.95] text-white">{event.title}</h2>
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

function Stat({ value, label }: { value: number; label: string }) {
    return (
        <div>
            <div className="text-3xl font-extrabold tracking-tight text-ink">{value.toLocaleString()}+</div>
            <div className="text-sm text-slate-400">{label}</div>
        </div>
    );
}

function SectionHead({ eyebrow, title, action }: { eyebrow?: string; title: string; action?: { label: string; href: string } }) {
    return (
        <div className="flex items-end justify-between gap-4">
            <div>
                {eyebrow && <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-300">{eyebrow}</p>}
                <h2 className="mt-1 text-[1.6rem] font-extrabold tracking-[-0.04em] text-ink md:text-4xl">{title}</h2>
            </div>
            {action && (
                <Link href={action.href} className="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-slate-500 hover:text-ink">
                    {action.label} <ArrowUpRight className="h-4 w-4" />
                </Link>
            )}
        </div>
    );
}

function Step({ n, icon, title, body }: { n: string; icon: ReactNode; title: string; body: string }) {
    return (
        <div className="flex gap-4 rounded-[1.5rem] border border-tint/[0.07] bg-card/70 p-5 md:flex-col md:p-7">
            <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-400/20">{icon}</div>
            <div>
                <p className="font-display text-lg italic text-slate-400">{n}</p>
                <h3 className="text-lg font-bold text-ink">{title}</h3>
                <p className="mt-1 text-sm leading-6 text-slate-500">{body}</p>
            </div>
        </div>
    );
}
