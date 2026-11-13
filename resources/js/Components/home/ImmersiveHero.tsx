import { StagePoster } from '@/Components/home/StagePoster';
import { Button } from '@/Components/ui/Button';
import { router } from '@inertiajs/react';
import { motion, useReducedMotion, Variants } from 'framer-motion';
import { ArrowRight, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';

const intro: Variants = {
    hidden: { opacity: 0, y: 28, filter: 'blur(8px)' },
    show: { opacity: 1, y: 0, filter: 'blur(0px)', transition: { duration: 0.9, ease: [0.22, 1, 0.36, 1] } },
};

export function ImmersiveHero({
    hostHref,
    stats,
}: {
    hostHref: string;
    stats: { events: number; tickets: number; organizers: number };
}) {
    const reduced = useReducedMotion();
    const [query, setQuery] = useState('');

    function search(e: FormEvent) {
        e.preventDefault();
        router.get('/events', query.trim() ? { q: query.trim() } : {});
    }

    return (
        <section className="relative -mt-16 overflow-hidden bg-[#0b0a1f] md:-mt-[4.5rem]">
            <StagePoster />
            {/* The photo sits over the CSS poster, which shows while it loads */}
            <motion.picture
                aria-hidden
                className="absolute inset-0"
                initial={reduced ? false : { opacity: 0, scale: 1.08 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ duration: 1.6, ease: [0.22, 1, 0.36, 1] }}
            >
                <source srcSet="/images/hero-stage.webp" type="image/webp" />
                <img src="/images/hero-stage.jpg" alt="" fetchPriority="high" decoding="async" className="h-full w-full object-cover object-[78%_center] lg:object-[72%_center]" />
            </motion.picture>
            {/* Keep the copy readable over the lights */}
            <div aria-hidden className="pointer-events-none absolute inset-0 bg-[linear-gradient(90deg,rgb(11_10_31/0.9)_0%,rgb(11_10_31/0.55)_35%,transparent_58%)] max-lg:bg-[linear-gradient(180deg,rgb(11_10_31/0.15)_0%,rgb(11_10_31/0.6)_45%,rgb(11_10_31/0.92)_100%)]" />
            <div aria-hidden className="pointer-events-none absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-canvas to-transparent" />

            <div className="container-page relative flex min-h-[100svh] items-end pb-24 pt-28 lg:items-center lg:pb-16">
                <motion.div
                    initial={reduced ? false : 'hidden'}
                    animate="show"
                    variants={{ hidden: {}, show: { transition: { staggerChildren: 0.16, delayChildren: 0.15 } } }}
                    className="max-w-2xl text-white"
                >
                    <motion.p variants={intro} className="inline-flex items-center gap-2.5 text-[12px] font-bold uppercase tracking-[0.32em] text-[#cbb8ff]">
                        <span className="relative flex h-2 w-2">
                            <span className="absolute inset-0 animate-ping rounded-full bg-[#a78bfa] motion-reduce:animate-none" />
                            <span className="relative h-2 w-2 rounded-full bg-[#a78bfa]" />
                        </span>
                        Every moment has a story.
                    </motion.p>
                    <motion.h1 variants={intro} className="mt-5 text-[clamp(3.2rem,8vw,6.4rem)] font-extrabold uppercase leading-[0.86] tracking-[-0.055em]">
                        Life happens{' '}
                        <span className="bg-gradient-to-r from-[#e2d6ff] via-[#a47dff] to-[#7C3AED] bg-clip-text pr-2 text-transparent">here.</span>
                    </motion.h1>
                    <motion.p variants={intro} className="mt-6 max-w-xl text-[15px] leading-7 text-white/75 md:text-lg md:leading-8">
                        Discover extraordinary experiences. Find concerts, festivals, conferences, sports and unforgettable events happening around you.
                    </motion.p>
                    <motion.div variants={intro} className="mt-8 grid gap-3 sm:flex sm:flex-wrap">
                        <Button href="/events" size="lg" className="w-full uppercase tracking-[0.08em] sm:w-auto">
                            Explore events <ArrowRight className="h-4 w-4" />
                        </Button>
                        <Button href={hostHref} variant="outline" size="lg" className="w-full sm:w-auto border-white/25 bg-white/10 uppercase tracking-[0.08em] text-white hover:border-white/40 hover:bg-white/15">
                            Host an event
                        </Button>
                    </motion.div>
                    <motion.form variants={intro} onSubmit={search} className="mt-6 flex max-w-md items-center rounded-full border border-white/15 bg-white/[0.07] p-1.5 pl-4 backdrop-blur-xl">
                        <Search className="h-4 w-4 shrink-0 text-white/60" />
                        <label className="min-w-0 flex-1">
                            <span className="sr-only">Search events</span>
                            <input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Events, artists, venues"
                                className="h-10 w-full bg-transparent px-3 text-[15px] text-white placeholder:text-white/50 focus:outline-none"
                            />
                        </label>
                        <button type="submit" className="h-10 shrink-0 rounded-full bg-white px-4 text-sm font-bold text-[#101126] transition-transform active:scale-95">
                            Search
                        </button>
                    </motion.form>
                    <motion.div variants={intro} className="mt-9 hidden gap-10 md:flex">
                        <HeroStat value={stats.events} label="Live events" />
                        <HeroStat value={stats.tickets} label="Tickets issued" />
                        <HeroStat value={stats.organizers} label="Organizers" />
                    </motion.div>
                </motion.div>
            </div>
        </section>
    );
}

function HeroStat({ value, label }: { value: number; label: string }) {
    return (
        <div>
            <p className="text-3xl font-extrabold tracking-tight">{value.toLocaleString()}</p>
            <p className="text-sm text-white/60">{label}</p>
        </div>
    );
}
