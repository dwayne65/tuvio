import { Logo } from '@/Components/Logo';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { Button } from '@/Components/ui/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { motion } from 'framer-motion';
import {
    ArrowRight, BadgeCheck, BarChart3, CalendarPlus, Compass, CreditCard, HeartHandshake, Lock, Megaphone,
    QrCode, ScanLine, ShieldCheck, Smartphone, Sparkles, Ticket, Wallet, Zap,
} from 'lucide-react';
import { ReactNode, useState } from 'react';

type Stats = { events: number; tickets: number; organizers: number; cities: number };

const values = [
    { icon: HeartHandshake, title: 'People first', body: 'Every feature starts with the fan at the gate and the organizer behind the stage, not with a spreadsheet.' },
    { icon: ShieldCheck, title: 'Trust is the product', body: 'Payments are verified server-side, every ticket carries a secret QR token and every shilling is tracked.' },
    { icon: Smartphone, title: 'Made for the phone', body: 'From discovery to the scan at the door, TUVIO is designed for the device already in your pocket.' },
    { icon: Zap, title: 'Fast, always', body: 'Instant checkout without sign-up, tickets in your inbox in seconds, and check-in that keeps queues moving.' },
];

const flows = {
    fans: [
        { icon: Compass, title: 'Discover', body: 'Browse concerts, conferences, parties and more by category, city or date.' },
        { icon: CreditCard, title: 'Checkout in one step', body: 'Pay with Mobile Money or card. No account required, promo codes welcome.' },
        { icon: Ticket, title: 'Get your ticket', body: 'Your QR ticket is emailed instantly and saved to your wallet.' },
        { icon: ScanLine, title: 'Walk right in', body: 'Show the QR code at the gate. Scanned once, valid once.' },
    ],
    organizers: [
        { icon: CalendarPlus, title: 'Create', body: 'Build a beautiful event page with ticket tiers, schedule and cover art.' },
        { icon: Megaphone, title: 'Promote', body: 'Share promoter links and discount codes, and track every click and sale.' },
        { icon: QrCode, title: 'Check in', body: 'Scan tickets from any phone with gates, staff roles and offline mode.' },
        { icon: Wallet, title: 'Get paid', body: 'Live sales, attendance reports and payouts straight to your account.' },
    ],
};

const trust = [
    { icon: Lock, title: 'Verified payments', body: 'We confirm every payment with the provider before a single ticket is issued.' },
    { icon: QrCode, title: 'Unforgeable tickets', body: 'Each QR code holds a random secure token, so it can’t be guessed or duplicated.' },
    { icon: BarChart3, title: 'Auditable money', body: 'A double-entry ledger records every sale, fee and payout so balances always add up.' },
    { icon: BadgeCheck, title: 'Verified reviews', body: 'Only real ticket buyers can rate an event, so ratings mean something.' },
];

function compact(n: number) {
    return new Intl.NumberFormat(undefined, { notation: n >= 10000 ? 'compact' : 'standard', maximumFractionDigits: 1 }).format(n);
}

function Eyebrow({ children }: { children: ReactNode }) {
    return <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-brand-300">{children}</p>;
}

function SectionTitle({ children, className }: { children: ReactNode; className?: string }) {
    return <h2 className={cn('mt-3 text-[2rem] font-extrabold leading-[1.05] tracking-[-0.045em] text-ink md:text-5xl', className)}>{children}</h2>;
}

export default function About({ stats }: PageProps<{ stats: Stats }>) {
    const [audience, setAudience] = useState<'fans' | 'organizers'>('fans');

    const figures = [
        { label: 'Live events', value: stats.events },
        { label: 'Tickets issued', value: stats.tickets },
        { label: 'Organizers', value: stats.organizers },
        { label: 'Cities', value: stats.cities },
    ];

    return (
        <PublicLayout>
            <Head title="About" />

            {/* Hero */}
            <section className="relative overflow-hidden">
                <div aria-hidden className="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_85%_0%,rgb(100_24_245/0.18),transparent_70%),radial-gradient(45%_40%_at_0%_30%,rgb(124_70_255/0.1),transparent_70%)]" />
                <div className="container-page relative grid items-center gap-10 pb-12 pt-6 md:py-24 lg:grid-cols-[1.15fr_0.85fr]">
                    <motion.div initial="hidden" animate="show" variants={{ hidden: {}, show: { transition: { staggerChildren: 0.08 } } }}>
                        <motion.p
                            variants={{ hidden: { opacity: 0, y: 12 }, show: { opacity: 1, y: 0 } }}
                            className="inline-flex items-center gap-1.5 rounded-full border border-brand-500/20 bg-brand-500/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-brand-300"
                        >
                            <Sparkles className="h-3.5 w-3.5" /> About TUVIO
                        </motion.p>
                        <motion.h1
                            variants={{ hidden: { opacity: 0, y: 16 }, show: { opacity: 1, y: 0 } }}
                            className="mt-5 text-[2.6rem] font-extrabold leading-[0.98] tracking-[-0.05em] text-ink sm:text-6xl md:text-7xl"
                        >
                            Life happens <span className="font-display font-normal italic tracking-normal text-gradient">here.</span>
                        </motion.h1>
                        <motion.p variants={{ hidden: { opacity: 0, y: 16 }, show: { opacity: 1, y: 0 } }} className="mt-5 max-w-xl text-lg leading-8 text-slate-500">
                            The first dance, the encore, the keynote that changed your mind. TUVIO is the ticketing and event platform that gets people
                            to the moments that matter, and gives organizers everything they need to create them.
                        </motion.p>
                        <motion.div variants={{ hidden: { opacity: 0, y: 16 }, show: { opacity: 1, y: 0 } }} className="mt-8 flex flex-wrap gap-3">
                            <Button href="/events" size="lg">Find an event <ArrowRight className="h-4 w-4" /></Button>
                            <Button href="/register" variant="outline" size="lg">Host with TUVIO</Button>
                        </motion.div>
                    </motion.div>

                    <motion.div
                        initial={{ opacity: 0, scale: 0.94, rotate: -2 }}
                        animate={{ opacity: 1, scale: 1, rotate: 0 }}
                        transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
                        className="relative mx-auto w-full max-w-sm"
                    >
                        <div className="absolute inset-6 rounded-[2.5rem] bg-brand-500/30 blur-3xl" />
                        <div className="relative overflow-hidden rounded-[2.25rem] bg-gradient-to-br from-[#0b1030] via-[#24115e] to-brand-700 p-7 text-white shadow-premium">
                            <div className="absolute -right-10 -top-10 h-44 w-44 rounded-full bg-white/10 blur-2xl" />
                            <div className="relative grid h-24 w-24 place-items-center rounded-[1.75rem] bg-white shadow-xl float-y">
                                <Logo variant="mark" className="h-16" />
                            </div>
                            <p className="relative mt-8 font-display text-[2.6rem] italic leading-none">Life Happens Here.</p>
                            <p className="relative mt-3 text-sm text-white/70">Discover · Buy · Scan · Celebrate</p>
                            <div className="relative mt-7 grid grid-cols-2 gap-2.5">
                                {['Mobile Money', 'Visa & Mastercard', 'QR check-in', 'Instant payouts'].map((t) => (
                                    <span key={t} className="rounded-2xl bg-white/10 px-3 py-2.5 text-xs font-semibold backdrop-blur">{t}</span>
                                ))}
                            </div>
                        </div>
                    </motion.div>
                </div>
            </section>

            {/* Live figures */}
            <section className="container-page">
                <RevealGroup className="grid grid-cols-2 overflow-hidden rounded-[1.75rem] border border-tint/[0.07] bg-card shadow-[var(--shadow-card)] md:grid-cols-4">
                    {figures.map((f, i) => (
                        <RevealItem
                            key={f.label}
                            className={cn('p-5 text-center md:p-7', i % 2 === 1 && 'border-l border-tint/[0.06]', i >= 2 && 'border-t border-tint/[0.06] md:border-t-0', i === 2 && 'md:border-l')}
                        >
                            <p className="text-3xl font-extrabold tracking-[-0.04em] text-ink md:text-4xl">{compact(f.value)}</p>
                            <p className="mt-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">{f.label}</p>
                        </RevealItem>
                    ))}
                </RevealGroup>
                <p className="mt-2 text-center text-xs text-slate-400">Live numbers from the platform, updated as it grows.</p>
            </section>

            {/* Mission */}
            <section className="container-page grid gap-10 py-16 md:py-24 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
                <Reveal>
                    <Eyebrow>Our mission</Eyebrow>
                    <SectionTitle>
                        Make every great event <span className="font-display font-normal italic tracking-normal text-brand-300">easy to reach.</span>
                    </SectionTitle>
                </Reveal>
                <Reveal delay={0.1} className="space-y-5 text-[17px] leading-8 text-slate-500">
                    <p>
                        Too many unforgettable nights start with a frustrating queue: cash-only gates, fake tickets, payments that never confirm and
                        organizers who can’t see what they’ve sold until the night is over.
                    </p>
                    <p>
                        We built TUVIO to fix that. Fans buy in seconds with the payment method they already use and carry tickets that can’t be
                        forged. Organizers get one place to publish, promote, sell, scan and get paid, with real numbers in real time.
                    </p>
                    <p className="font-semibold text-ink">Built in Kampala for Africa, ready for the world.</p>
                </Reveal>
            </section>

            {/* Values */}
            <section className="container-page">
                <Reveal className="max-w-2xl">
                    <Eyebrow>What we stand for</Eyebrow>
                    <SectionTitle>Four promises we keep.</SectionTitle>
                </Reveal>
                <RevealGroup className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {values.map((v, i) => (
                        <RevealItem key={v.title}>
                            <div className="group h-full rounded-[1.5rem] border border-tint/[0.07] bg-card p-6 shadow-[var(--shadow-card)] transition-transform duration-300 hover:-translate-y-1">
                                <div className="flex items-center justify-between">
                                    <span className="grid h-12 w-12 place-items-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-700 text-white glow-blue">
                                        <v.icon className="h-5 w-5" />
                                    </span>
                                    <span className="font-display text-3xl italic text-slate-300">0{i + 1}</span>
                                </div>
                                <h3 className="mt-5 text-lg font-bold text-ink">{v.title}</h3>
                                <p className="mt-2 text-sm leading-6 text-slate-500">{v.body}</p>
                            </div>
                        </RevealItem>
                    ))}
                </RevealGroup>
            </section>

            {/* How it works */}
            <section className="container-page py-16 md:py-24">
                <Reveal className="flex flex-col items-start justify-between gap-5 md:flex-row md:items-end">
                    <div>
                        <Eyebrow>How it works</Eyebrow>
                        <SectionTitle>One platform, two sides of the night.</SectionTitle>
                    </div>
                    <div className="flex rounded-full border border-tint/[0.08] bg-card p-1" role="tablist">
                        {(['fans', 'organizers'] as const).map((a) => (
                            <button
                                key={a}
                                role="tab"
                                aria-selected={audience === a}
                                onClick={() => setAudience(a)}
                                className={cn('relative rounded-full px-5 py-2 text-sm font-semibold capitalize transition-colors', audience === a ? 'text-white' : 'text-slate-500 hover:text-ink')}
                            >
                                {audience === a && <motion.span layoutId="about-tab" className="absolute inset-0 rounded-full bg-brand-500" transition={{ type: 'spring', bounce: 0.2, duration: 0.5 }} />}
                                <span className="relative">For {a}</span>
                            </button>
                        ))}
                    </div>
                </Reveal>

                <div className="relative mt-10">
                    <div aria-hidden className="absolute left-0 right-0 top-7 hidden h-px bg-gradient-to-r from-transparent via-brand-500/40 to-transparent lg:block" />
                    <motion.ol key={audience} initial="hidden" animate="show" variants={{ hidden: {}, show: { transition: { staggerChildren: 0.08 } } }} className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {flows[audience].map((s, i) => (
                            <motion.li
                                key={s.title}
                                variants={{ hidden: { opacity: 0, y: 18 }, show: { opacity: 1, y: 0 } }}
                                className="relative rounded-[1.5rem] border border-tint/[0.07] bg-card/80 p-6"
                            >
                                <span className="relative z-10 grid h-14 w-14 place-items-center rounded-full border-4 border-canvas bg-ink text-canvas">
                                    <s.icon className="h-5 w-5" />
                                </span>
                                <p className="mt-5 text-xs font-bold uppercase tracking-[0.16em] text-brand-300">Step {i + 1}</p>
                                <h3 className="mt-1 text-lg font-bold text-ink">{s.title}</h3>
                                <p className="mt-2 text-sm leading-6 text-slate-500">{s.body}</p>
                            </motion.li>
                        ))}
                    </motion.ol>
                </div>
            </section>

            {/* Trust */}
            <section className="container-page">
                <Reveal>
                    <div className="relative overflow-hidden rounded-[2rem] bg-gradient-to-br from-[#0b1030] via-[#1b0f4a] to-[#2c0a6e] p-6 text-white shadow-premium sm:p-10 md:p-14">
                        <div className="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-brand-500/30 blur-3xl" />
                        <div className="absolute -bottom-32 -left-16 h-72 w-72 rounded-full bg-[#9b6bff]/20 blur-3xl" />
                        <div className="relative grid gap-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-14">
                            <div>
                                <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-white/60">Security & trust</p>
                                <h2 className="mt-3 text-[2rem] font-extrabold leading-[1.05] tracking-[-0.045em] md:text-5xl">
                                    Built so nobody gets <span className="font-display font-normal italic tracking-normal text-[#c4a8ff]">left outside.</span>
                                </h2>
                                <p className="mt-4 text-white/70">
                                    Fake tickets and lost payments ruin nights. Our checks run quietly in the background so the only thing you notice is how smooth it all feels.
                                </p>
                            </div>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {trust.map((t) => (
                                    <div key={t.title} className="rounded-[1.25rem] border border-white/10 bg-white/[0.06] p-5 backdrop-blur">
                                        <t.icon className="h-5 w-5 text-[#c4a8ff]" />
                                        <h3 className="mt-3 font-bold">{t.title}</h3>
                                        <p className="mt-1 text-sm leading-6 text-white/65">{t.body}</p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </Reveal>
            </section>

            {/* CTA */}
            <section className="container-page py-16 md:py-24">
                <Reveal className="text-center">
                    <Logo variant="mark" className="mx-auto h-14" />
                    <h2 className="mx-auto mt-6 max-w-2xl text-[2.2rem] font-extrabold leading-[1.02] tracking-[-0.05em] text-ink md:text-6xl">
                        Your next story starts <span className="font-display font-normal italic tracking-normal text-gradient">tonight.</span>
                    </h2>
                    <p className="mx-auto mt-4 max-w-md text-slate-500">Whether you’re buying a ticket or selling out a stadium, there’s a place for you here.</p>
                    <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                        <Button href="/events" size="lg">Explore events <ArrowRight className="h-4 w-4" /></Button>
                        <Button href="/contact" variant="outline" size="lg">Talk to us</Button>
                    </div>
                    <p className="mt-10 text-xs text-slate-400">
                        <Link href="/terms" className="hover:text-ink">Terms of Service</Link>
                        <span className="mx-2">·</span>
                        <Link href="/privacy" className="hover:text-ink">Privacy Policy</Link>
                    </p>
                </Reveal>
            </section>
        </PublicLayout>
    );
}
