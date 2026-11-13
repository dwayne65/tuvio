import { Reveal } from '@/Components/motion/Reveal';
import PublicLayout from '@/Layouts/PublicLayout';
import { cn } from '@/lib/utils';
import { Head, Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ArrowUp, CalendarClock, ChevronDown, LucideIcon, Mail, Printer } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';

export type LegalSection = { id: string; title: string; body: ReactNode };

/** Shared layout for policy pages: hero, plain-language summary, sticky contents and numbered sections. */
export function LegalDocument({
    title,
    accent,
    intro,
    updated,
    summary,
    sections,
    icon: Icon,
}: {
    title: string;
    accent: string;
    intro: string;
    updated: string;
    summary: { icon: LucideIcon; text: string }[];
    sections: LegalSection[];
    icon: LucideIcon;
}) {
    const [active, setActive] = useState(sections[0]?.id);
    const [tocOpen, setTocOpen] = useState(false);

    // Highlight the section currently in view.
    useEffect(() => {
        const io = new IntersectionObserver(
            (entries) => {
                const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
                if (visible[0]) setActive(visible[0].target.id);
            },
            { rootMargin: '-20% 0px -70% 0px' },
        );
        sections.forEach((s) => {
            const el = document.getElementById(s.id);
            if (el) io.observe(el);
        });
        return () => io.disconnect();
    }, [sections]);

    const current = sections.findIndex((s) => s.id === active);

    const toc = (
        <ol className="space-y-0.5">
            {sections.map((s, i) => (
                <li key={s.id}>
                    <a
                        href={`#${s.id}`}
                        onClick={() => setTocOpen(false)}
                        className={cn(
                            'flex gap-3 rounded-xl px-3 py-2 text-sm transition-colors',
                            active === s.id ? 'bg-brand-500/10 font-semibold text-brand-300' : 'text-slate-500 hover:bg-tint/[0.04] hover:text-ink',
                        )}
                    >
                        <span className="w-5 shrink-0 tabular-nums opacity-60">{String(i + 1).padStart(2, '0')}</span>
                        {s.title}
                    </a>
                </li>
            ))}
        </ol>
    );

    return (
        <PublicLayout>
            <Head title={`${title} ${accent}`} />

            {/* Hero */}
            <section className="relative overflow-hidden border-b border-tint/[0.06]">
                <div aria-hidden className="pointer-events-none absolute inset-0 bg-[radial-gradient(55%_60%_at_90%_0%,rgb(100_24_245/0.16),transparent_70%)]" />
                <div aria-hidden className="dot-grid pointer-events-none absolute inset-0 opacity-40 [mask-image:linear-gradient(to_bottom,black,transparent)]" />
                <div className="container-page relative max-w-5xl pb-10 pt-6 md:pb-16 md:pt-16">
                    <motion.span
                        initial={{ opacity: 0, scale: 0.8 }}
                        animate={{ opacity: 1, scale: 1 }}
                        className="grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-brand-400 to-brand-700 text-white glow-blue"
                    >
                        <Icon className="h-6 w-6" />
                    </motion.span>
                    <motion.h1
                        initial={{ opacity: 0, y: 14 }}
                        animate={{ opacity: 1, y: 0 }}
                        transition={{ delay: 0.05 }}
                        className="mt-5 text-[2.5rem] font-extrabold leading-[1] tracking-[-0.05em] text-ink md:text-6xl"
                    >
                        {title} <span className="font-display font-normal italic tracking-normal text-gradient">{accent}</span>
                    </motion.h1>
                    <motion.p initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} transition={{ delay: 0.1 }} className="mt-4 max-w-2xl text-lg leading-8 text-slate-500">
                        {intro}
                    </motion.p>
                    <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.15 }} className="mt-6 flex flex-wrap items-center gap-2 text-xs font-semibold">
                        <span className="inline-flex items-center gap-1.5 rounded-full border border-tint/[0.08] bg-card px-3 py-1.5 text-slate-500">
                            <CalendarClock className="h-3.5 w-3.5" /> Last updated {updated}
                        </span>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="inline-flex items-center gap-1.5 rounded-full border border-tint/[0.08] bg-card px-3 py-1.5 text-slate-500 hover:text-ink print:hidden"
                        >
                            <Printer className="h-3.5 w-3.5" /> Print
                        </button>
                    </motion.div>
                </div>
            </section>

            <div className="container-page max-w-5xl py-8 md:py-14">
                {/* The short version */}
                <Reveal>
                    <div className="rounded-[1.75rem] border border-brand-500/20 bg-gradient-to-br from-brand-500/[0.08] to-transparent p-5 sm:p-7">
                        <p className="text-[11px] font-bold uppercase tracking-[0.2em] text-brand-300">The short version</p>
                        <ul className="mt-4 grid gap-3 sm:grid-cols-2">
                            {summary.map((s) => (
                                <li key={s.text} className="flex gap-3 rounded-2xl bg-card/80 p-3.5 text-sm leading-6 text-slate-600">
                                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-500/10 text-brand-300">
                                        <s.icon className="h-4 w-4" />
                                    </span>
                                    {s.text}
                                </li>
                            ))}
                        </ul>
                        <p className="mt-4 text-xs text-slate-400">This summary helps you find your way. The full text below is what applies.</p>
                    </div>
                </Reveal>

                {/* Mobile contents */}
                <div className="sticky top-[4.25rem] z-30 mt-6 lg:hidden print:hidden">
                    <button
                        type="button"
                        onClick={() => setTocOpen(!tocOpen)}
                        aria-expanded={tocOpen}
                        className="glass flex w-full items-center justify-between gap-3 rounded-2xl px-4 py-3 text-left text-sm shadow-premium"
                    >
                        <span className="min-w-0">
                            <span className="block text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">
                                Section {current + 1} of {sections.length}
                            </span>
                            <span className="block truncate font-semibold text-ink">{sections[current]?.title}</span>
                        </span>
                        <ChevronDown className={cn('h-5 w-5 shrink-0 text-slate-400 transition-transform', tocOpen && 'rotate-180')} />
                    </button>
                    <AnimatePresence>
                        {tocOpen && (
                            <motion.div
                                initial={{ opacity: 0, y: -6 }}
                                animate={{ opacity: 1, y: 0 }}
                                exit={{ opacity: 0, y: -6 }}
                                className="glass absolute inset-x-0 top-full mt-2 max-h-[60vh] overflow-y-auto rounded-2xl p-2 shadow-premium"
                            >
                                {toc}
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>

                <div className="mt-6 grid gap-10 lg:mt-12 lg:grid-cols-[15rem_1fr] lg:gap-14">
                    <aside className="hidden lg:block print:hidden">
                        <div className="sticky top-24">
                            <p className="mb-3 px-3 text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Contents</p>
                            {toc}
                        </div>
                    </aside>

                    <article className="min-w-0">
                        {sections.map((s, i) => (
                            <section key={s.id} id={s.id} className="scroll-mt-40 border-b border-tint/[0.06] py-8 first:pt-0 last:border-0 lg:scroll-mt-24">
                                <h2 className="flex items-baseline gap-3 text-xl font-extrabold tracking-[-0.03em] text-ink md:text-2xl">
                                    <span className="font-display text-2xl font-normal italic text-brand-300 md:text-3xl">{String(i + 1).padStart(2, '0')}</span>
                                    {s.title}
                                </h2>
                                <div className="legal-prose mt-4 space-y-4 text-[15.5px] leading-7 text-slate-600">{s.body}</div>
                            </section>
                        ))}

                        <div className="mt-6 flex flex-col items-start justify-between gap-4 rounded-[1.5rem] border border-tint/[0.07] bg-card p-5 sm:flex-row sm:items-center sm:p-6">
                            <div className="flex items-center gap-3">
                                <span className="grid h-11 w-11 place-items-center rounded-full bg-brand-500/10 text-brand-300"><Mail className="h-5 w-5" /></span>
                                <div>
                                    <p className="font-bold text-ink">Questions about this policy?</p>
                                    <p className="text-sm text-slate-500">Write to us and a real person will reply.</p>
                                </div>
                            </div>
                            <Link href="/contact" className="inline-flex h-10 items-center rounded-full bg-ink px-5 text-sm font-semibold text-canvas">Contact us</Link>
                        </div>

                        <div className="mt-8 flex items-center justify-between text-sm print:hidden">
                            <div className="flex gap-4 text-slate-400">
                                <Link href="/terms" className="hover:text-ink">Terms</Link>
                                <Link href="/privacy" className="hover:text-ink">Privacy</Link>
                                <Link href="/about" className="hover:text-ink">About</Link>
                            </div>
                            <a href="#top" onClick={(e) => { e.preventDefault(); window.scrollTo({ top: 0, behavior: 'smooth' }); }} className="inline-flex items-center gap-1 font-semibold text-brand-300">
                                Back to top <ArrowUp className="h-4 w-4" />
                            </a>
                        </div>
                    </article>
                </div>
            </div>
        </PublicLayout>
    );
}
