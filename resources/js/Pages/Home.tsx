import { categoryIcon } from '@/Components/CategoryIcon';
import { EventCard } from '@/Components/EventCard';
import { AdmissionPass } from '@/Components/home/AdmissionPass';
import { ImmersiveHero } from '@/Components/home/ImmersiveHero';
import { Button } from '@/Components/ui/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { fadeUp, stagger } from '@/lib/motion';
import { cn } from '@/lib/utils';
import { Category, EventSummary, PageProps } from '@/types';
import { Link, Head, router } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import {
    Activity, ArrowRight, ArrowUpRight, ChevronLeft, ChevronRight, Compass, CreditCard, LayoutDashboard, PartyPopper, QrCode,
    ScanLine, Search, ShieldCheck, Smartphone, Ticket, Users, Wallet,
} from 'lucide-react';
import { ReactNode, useRef } from 'react';

type HomeCategory = Category & { upcoming_count: number };

type Props = {
    categories: HomeCategory[];
    spotlightEvents: EventSummary[];
    stats: { events: number; tickets: number; organizers: number };
};

const reveal = { initial: 'hidden', whileInView: 'show', viewport: { once: true, margin: '-12% 0px' } } as const;

export default function Home({ auth, categories, spotlightEvents, stats }: PageProps<Props>) {
    const hostHref = auth.user ? '/dashboard/events/create' : '/register';
    const featured = spotlightEvents.slice(0, 12);
    const passEvent = spotlightEvents.find((event) => event.availability !== 'sold_out') ?? spotlightEvents[0] ?? null;

    return (
        <PublicLayout floatingNav>
            <Head title="Life Happens Here." />

            <ImmersiveHero hostHref={hostHref} stats={stats} />

            {/* Discover what's happening */}
            <section id="discover" className="container-page scroll-mt-24 pt-16 md:pt-24">
                <SectionHead eyebrow="Discover what’s happening" title="Featured events" action={{ label: 'See all events', href: '/events' }} />
                {featured.length > 0 ? (
                    <EventCarousel events={featured} />
                ) : (
                    <div className="mt-8 rounded-[1.75rem] border border-dashed border-tint/15 p-10 text-center">
                        <p className="font-semibold text-ink">No events are on sale right now.</p>
                        <p className="mt-1 text-sm text-slate-500">Check back soon, or be the first to host one.</p>
                        <Button href={hostHref} className="mt-5">Host an event</Button>
                    </div>
                )}
            </section>

            {/* Categories */}
            <section className="container-page pt-16 md:pt-28">
                <SectionHead eyebrow="Find your scene" title="Explore categories" action={{ label: 'All categories', href: '/categories' }} />
                <motion.div {...reveal} variants={stagger(0.06)} className="mt-7 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                    {categories.map((category) => (
                        <CategoryCard key={category.id} category={category} />
                    ))}
                </motion.div>
            </section>

            {/* How it works */}
            <section id="how-it-works" className="container-page scroll-mt-24 pt-16 md:pt-28">
                <SectionHead eyebrow="Three steps to the front row" title="How TUVIO works" />
                <motion.ol {...reveal} variants={stagger(0.12)} className="relative mt-8 grid gap-4 md:grid-cols-3 md:gap-6">
                    <div aria-hidden className="absolute left-[16%] right-[16%] top-11 hidden h-px bg-gradient-to-r from-transparent via-brand-500/40 to-transparent md:block" />
                    <Step n="01" icon={<Search className="h-5 w-5" />} title="Discover an event" body="Browse concerts, festivals, conferences and more, near you or anywhere you’re headed." />
                    <Step n="02" icon={<Ticket className="h-5 w-5" />} title="Secure your ticket" body="Pay with Mobile Money or card. Your QR ticket arrives by email and lives in your account." />
                    <Step n="03" icon={<PartyPopper className="h-5 w-5" />} title="Experience the moment" body="Show your QR code at the gate, scan in once, and enjoy the night." />
                </motion.ol>
            </section>

            {/* The ticket */}
            <section className="relative overflow-hidden pt-16 md:pt-28">
                <div aria-hidden className="pointer-events-none absolute inset-x-0 top-1/3 h-2/3 bg-[radial-gradient(50%_50%_at_60%_50%,var(--glow-a),transparent_70%)]" />
                <FloatingPasses />
                <div className="container-page relative grid items-center gap-12 lg:grid-cols-[0.9fr_1.1fr]">
                    <motion.div {...reveal} variants={stagger(0.08)}>
                        <motion.p variants={fadeUp} className="text-xs font-bold uppercase tracking-[0.18em] text-brand-300">Your digital pass</motion.p>
                        <motion.h2 variants={fadeUp} className="mt-2 text-[2rem] font-extrabold leading-[1.02] tracking-[-0.045em] text-ink md:text-5xl">
                            A ticket worth <span className="font-display font-normal italic text-gradient">keeping.</span>
                        </motion.h2>
                        <motion.p variants={fadeUp} className="mt-4 max-w-md text-[15px] leading-7 text-slate-500 md:text-lg">
                            Every TUVIO ticket carries its own secure QR code. It’s verified at the gate in a second, and it can only be used once.
                        </motion.p>
                        <motion.ul variants={fadeUp} className="mt-6 grid gap-3 text-sm text-slate-600">
                            <PassPoint icon={<Wallet className="h-4 w-4" />}>Delivered instantly to your email and account</PassPoint>
                            <PassPoint icon={<ScanLine className="h-4 w-4" />}>Scans even when the venue’s signal drops</PassPoint>
                            <PassPoint icon={<ShieldCheck className="h-4 w-4" />}>One scan per ticket, so copies don’t get in</PassPoint>
                        </motion.ul>
                    </motion.div>
                    <motion.div initial={{ opacity: 0, y: 40 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, margin: '-12% 0px' }} transition={{ duration: 0.8, ease: [0.22, 1, 0.36, 1] }}>
                        <AdmissionPass event={passEvent} />
                    </motion.div>
                </div>
            </section>

            {/* For organizers */}
            <section className="container-page pt-16 md:pt-28">
                <div className="relative overflow-hidden rounded-[2rem] bg-[#101126] px-6 py-12 text-white md:px-14 md:py-16">
                    <div aria-hidden className="absolute -right-24 -top-28 h-96 w-96 rounded-full bg-[#6415F5]/35 blur-3xl" />
                    <div aria-hidden className="absolute -bottom-32 left-1/4 h-72 w-72 rounded-full bg-[#7C3AED]/25 blur-3xl" />
                    <div aria-hidden className="absolute inset-0 dot-grid opacity-25" />
                    <div className="relative grid gap-10 lg:grid-cols-[1.05fr_0.95fr] lg:items-center">
                        <motion.div {...reveal} variants={stagger(0.08)}>
                            <motion.p variants={fadeUp} className="text-xs font-bold uppercase tracking-[0.2em] text-[#cbb8ff]">For event organizers</motion.p>
                            <motion.h2 variants={fadeUp} className="mt-3 text-[2.1rem] font-extrabold uppercase leading-[0.95] tracking-[-0.045em] md:text-6xl">
                                Your event deserves the <span className="bg-gradient-to-r from-[#e2d6ff] to-[#a47dff] bg-clip-text text-transparent">spotlight.</span>
                            </motion.h2>
                            <motion.p variants={fadeUp} className="mt-5 max-w-lg text-[15px] leading-7 text-white/70 md:text-lg">
                                Create events, sell tickets and manage your audience from one powerful platform.
                            </motion.p>
                            <motion.div variants={fadeUp} className="mt-8 flex flex-wrap gap-3">
                                <Button href={hostHref} size="lg" className="uppercase tracking-[0.08em]">
                                    Start creating events <ArrowRight className="h-4 w-4" />
                                </Button>
                                <Button href="/pricing" variant="outline" size="lg" className="border-white/20 bg-white/[0.06] text-white hover:bg-white/10">
                                    See pricing
                                </Button>
                            </motion.div>
                        </motion.div>
                        <motion.div {...reveal} variants={stagger(0.07)} className="grid grid-cols-2 gap-3">
                            <OrganizerTile icon={<Ticket className="h-5 w-5" />} title="Ticket tiers" body="VIP, early bird, promo codes and sales windows." />
                            <OrganizerTile icon={<ScanLine className="h-5 w-5" />} title="Gate scanning" body="Phone scanners that keep working offline." />
                            <OrganizerTile icon={<Users className="h-5 w-5" />} title="Your team" body="Invite staff with the right role for each job." />
                            <OrganizerTile icon={<Activity className="h-5 w-5" />} title="Live insights" body="Sales, attendance and payouts as they happen." />
                        </motion.div>
                    </div>
                </div>
            </section>

            {/* Why TUVIO */}
            <section className="container-page pt-16 md:pt-28">
                <SectionHead eyebrow="Built for how Africa buys tickets" title="Why choose TUVIO" />
                <motion.div {...reveal} variants={stagger(0.06)} className="mt-7 grid gap-3 sm:grid-cols-2 md:gap-4 lg:grid-cols-3">
                    <Reason icon={<QrCode className="h-5 w-5" />} title="Secure QR-code tickets" body="Unique, signed codes that can’t be reused at the gate." />
                    <Reason icon={<Smartphone className="h-5 w-5" />} title="Mobile Money payments" body="Pay in seconds from the wallet you already use." />
                    <Reason icon={<CreditCard className="h-5 w-5" />} title="Visa and Mastercard" body="Card payments for local and international guests." />
                    <Reason icon={<LayoutDashboard className="h-5 w-5" />} title="Easy event management" body="Publish, edit and duplicate events in a few clicks." />
                    <Reason icon={<Activity className="h-5 w-5" />} title="Real-time ticket tracking" body="See every sale and check-in the moment it happens." />
                    <Reason icon={<Compass className="h-5 w-5" />} title="Simple event discovery" body="Search by category, city or date and find your night fast." />
                </motion.div>
            </section>

            {/* Final CTA */}
            <section className="container-page pb-8 pt-16 md:pb-16 md:pt-28">
                <div className="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#6415F5] via-[#5a14e0] to-[#101126] px-6 py-14 text-center text-white md:px-16 md:py-20">
                    <div aria-hidden className="absolute inset-0 dot-grid opacity-30" />
                    <div aria-hidden className="absolute left-1/2 top-0 h-72 w-[40rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-white/20 blur-3xl" />
                    <motion.div {...reveal} variants={stagger(0.08)} className="relative mx-auto max-w-3xl">
                        <motion.h2 variants={fadeUp} className="text-[2rem] font-extrabold uppercase leading-[0.98] tracking-[-0.045em] md:text-6xl">
                            Your next unforgettable moment starts here.
                        </motion.h2>
                        <motion.div variants={fadeUp} className="mt-8 flex justify-center">
                            <Button href="/events" variant="secondary" size="lg" className="bg-white uppercase tracking-[0.08em] text-[#101126]">
                                Discover events <ArrowRight className="h-4 w-4" />
                            </Button>
                        </motion.div>
                        <motion.form
                            variants={fadeUp}
                            onSubmit={(e) => {
                                e.preventDefault();
                                const email = (e.currentTarget.elements.namedItem('email') as HTMLInputElement).value;
                                router.post('/newsletter', { email }, { preserveScroll: true });
                            }}
                            className="mx-auto mt-10 flex max-w-md items-center gap-2 rounded-full border border-white/20 bg-white/10 p-1.5 backdrop-blur"
                        >
                            <input
                                name="email"
                                type="email"
                                required
                                placeholder="Get the best events weekly"
                                aria-label="Email address"
                                className="h-10 min-w-0 flex-1 bg-transparent pl-4 text-[15px] text-white placeholder:text-white/60 focus:outline-none"
                            />
                            <button type="submit" className="h-10 shrink-0 rounded-full bg-white/15 px-4 text-sm font-bold text-white transition-colors hover:bg-white/25">
                                Subscribe
                            </button>
                        </motion.form>
                    </motion.div>
                </div>
            </section>
        </PublicLayout>
    );
}

function EventCarousel({ events }: { events: EventSummary[] }) {
    const track = useRef<HTMLDivElement>(null);
    const reduced = useReducedMotion();
    const scroll = (direction: -1 | 1) =>
        track.current?.scrollBy({ left: direction * track.current.clientWidth * 0.8, behavior: reduced ? 'auto' : 'smooth' });

    return (
        <div className="relative mt-7">
            <div className="absolute -top-[4.25rem] right-36 hidden gap-2 md:flex">
                <CarouselButton label="Previous events" onClick={() => scroll(-1)}><ChevronLeft className="h-5 w-5" /></CarouselButton>
                <CarouselButton label="Next events" onClick={() => scroll(1)}><ChevronRight className="h-5 w-5" /></CarouselButton>
            </div>
            <div ref={track} className="scroller -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-px-4 px-4 pb-4 sm:-mx-6 sm:scroll-px-6 sm:px-6 md:gap-6 lg:-mx-8 lg:scroll-px-8 lg:px-8">
                {events.map((event, i) => (
                    <motion.div
                        key={event.id}
                        initial={{ opacity: 0, y: 32 }}
                        whileInView={{ opacity: 1, y: 0 }}
                        viewport={{ once: true, margin: '-8% 0px' }}
                        transition={{ duration: 0.6, delay: Math.min(i, 4) * 0.07, ease: [0.22, 1, 0.36, 1] }}
                        className="w-[78vw] max-w-[21rem] shrink-0 sm:w-[20rem]"
                    >
                        <EventCard event={event} />
                    </motion.div>
                ))}
            </div>
        </div>
    );
}

function CarouselButton({ label, onClick, children }: { label: string; onClick: () => void; children: ReactNode }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className="grid h-11 w-11 place-items-center rounded-full border border-tint/10 bg-card text-ink shadow-[var(--shadow-card)] transition-colors hover:border-brand-500/40 hover:text-brand-300"
        >
            {children}
        </button>
    );
}

function CategoryCard({ category }: { category: HomeCategory }) {
    const Icon = categoryIcon(category.icon);
    const color = category.color ?? '#6415F5';

    return (
        <motion.div variants={fadeUp}>
            <Link
                href={`/events?category=${category.slug}`}
                className="group relative flex aspect-[4/3.4] flex-col justify-end overflow-hidden rounded-[1.5rem] border border-white/[0.06] bg-[#101126] p-4 text-white transition-transform duration-500 hover:-translate-y-1 sm:aspect-[4/3.2] sm:p-5"
            >
                <div
                    aria-hidden
                    className="absolute inset-0 opacity-80 transition-opacity duration-500 group-hover:opacity-100"
                    style={{ background: `radial-gradient(90% 80% at 85% 10%, ${color}, transparent 70%), radial-gradient(70% 60% at 0% 100%, ${color}55, transparent 70%)` }}
                />
                <Icon aria-hidden className="absolute -right-4 -top-4 h-28 w-28 text-white/[0.13] transition-transform duration-700 group-hover:-rotate-12 group-hover:scale-110 sm:h-36 sm:w-36" />
                <div className="relative">
                    <span className="mb-3 grid h-10 w-10 place-items-center rounded-xl border border-white/20 bg-white/10 backdrop-blur">
                        <Icon className="h-5 w-5" />
                    </span>
                    <h3 className="text-[15px] font-bold leading-tight sm:text-lg">{category.name}</h3>
                    <p className="mt-0.5 flex items-center gap-1 text-xs text-white/70">
                        {category.upcoming_count > 0 ? `${category.upcoming_count} upcoming` : 'Coming soon'}
                        <ArrowUpRight className="h-3.5 w-3.5 opacity-0 transition-opacity group-hover:opacity-100" />
                    </p>
                </div>
            </Link>
        </motion.div>
    );
}

function SectionHead({ eyebrow, title, action }: { eyebrow?: string; title: string; action?: { label: string; href: string } }) {
    return (
        <div className="flex items-end justify-between gap-4">
            <div className="min-w-0">
                {eyebrow && <p className="text-xs font-bold uppercase tracking-[0.18em] text-brand-300">{eyebrow}</p>}
                <h2 className="mt-1.5 text-[1.75rem] font-extrabold tracking-[-0.045em] text-ink md:text-[2.75rem] md:leading-[1.05]">{title}</h2>
            </div>
            {action && (
                <Link href={action.href} className="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-slate-500 hover:text-brand-300">
                    <span className="sm:hidden">See all</span>
                    <span className="hidden sm:inline">{action.label}</span>
                    <ArrowUpRight className="h-4 w-4" />
                </Link>
            )}
        </div>
    );
}

function Step({ n, icon, title, body }: { n: string; icon: ReactNode; title: string; body: string }) {
    return (
        <motion.li variants={fadeUp} className="relative flex gap-4 rounded-[1.5rem] border border-tint/[0.07] bg-card/80 p-5 md:flex-col md:p-7">
            <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-500 text-white glow-blue">{icon}</div>
            <div>
                <p className="font-display text-lg italic text-slate-400">{n}</p>
                <h3 className="text-lg font-bold text-ink">{title}</h3>
                <p className="mt-1 text-sm leading-6 text-slate-500">{body}</p>
            </div>
        </motion.li>
    );
}

function PassPoint({ icon, children }: { icon: ReactNode; children: ReactNode }) {
    return (
        <li className="flex items-center gap-3">
            <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-500/10 text-brand-300">{icon}</span>
            {children}
        </li>
    );
}

function OrganizerTile({ icon, title, body }: { icon: ReactNode; title: string; body: string }) {
    return (
        <motion.div variants={fadeUp} className="rounded-[1.25rem] border border-white/10 bg-white/[0.05] p-4 backdrop-blur sm:p-5">
            <span className="grid h-10 w-10 place-items-center rounded-xl bg-[#7C3AED]/30 text-[#d9ccff]">{icon}</span>
            <h3 className="mt-3 text-[15px] font-bold">{title}</h3>
            <p className="mt-1 text-[13px] leading-5 text-white/60">{body}</p>
        </motion.div>
    );
}

function Reason({ icon, title, body }: { icon: ReactNode; title: string; body: string }) {
    return (
        <motion.div
            variants={fadeUp}
            className="group flex gap-4 rounded-[1.5rem] border border-tint/[0.07] bg-card/80 p-5 transition-colors hover:border-brand-500/30 md:p-6"
        >
            <span className="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-brand-500/10 text-brand-300 transition-colors group-hover:bg-brand-500 group-hover:text-white">
                {icon}
            </span>
            <div>
                <h3 className="font-bold text-ink">{title}</h3>
                <p className="mt-1 text-sm leading-6 text-slate-500">{body}</p>
            </div>
        </motion.div>
    );
}

/** Soft, out-of-focus passes drifting behind the ticket section. */
function FloatingPasses() {
    return (
        <div aria-hidden className="pointer-events-none absolute inset-0 hidden lg:block">
            {[
                { className: 'left-[4%] top-[20%] w-40', r: '-14deg', delay: '0s' },
                { className: 'right-[6%] top-[12%] w-32', r: '12deg', delay: '-2.5s' },
                { className: 'bottom-[6%] right-[38%] w-28', r: '-6deg', delay: '-5s' },
            ].map((pass) => (
                <div
                    key={pass.className}
                    className={cn('float-pass absolute aspect-[2.2/1] rounded-2xl bg-gradient-to-br from-[#7C3AED]/30 to-[#6415F5]/10 blur-[2px] ring-1 ring-brand-500/20', pass.className)}
                    style={{ ['--r' as string]: pass.r, animationDelay: pass.delay }}
                >
                    <div className="absolute inset-y-3 right-[28%] border-l border-dashed border-brand-500/30" />
                    <div className="absolute right-[8%] top-1/2 grid h-[45%] aspect-square -translate-y-1/2 grid-cols-3 gap-0.5 opacity-50">
                        {Array.from({ length: 9 }, (_, i) => (
                            <span key={i} className={i % 2 === 0 ? 'rounded-[1px] bg-brand-500/60' : ''} />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    );
}
