import { Toaster } from '@/Components/app/Toaster';
import { Logo } from '@/Components/Logo';
import { MobileTabBar } from '@/Components/MobileTabBar';
import { Button } from '@/Components/ui/Button';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Menu, X } from 'lucide-react';
import { ReactNode, useEffect, useState } from 'react';

const nav = [
    { label: 'Discover', href: '/events' },
    { label: 'Categories', href: '/categories' },
    { label: 'How it works', href: '/#how-it-works' },
    { label: 'Pricing', href: '/pricing' },
];

export default function PublicLayout({
    children,
    immersive = false,
    hideTabBar = false,
    floatingNav = false,
}: {
    children: ReactNode;
    /** Hide the mobile app bar so the page can draw its own over a hero. */
    immersive?: boolean;
    /** Hide the mobile tab bar, e.g. when the page shows its own sticky action bar. */
    hideTabBar?: boolean;
    /** Keep the frosted nav even at the top, for pages that open on a full-bleed visual. */
    floatingNav?: boolean;
}) {
    const { auth } = usePage<PageProps>().props;
    const path = usePage().url.split('?')[0];
    const [open, setOpen] = useState(false);
    const [scrolled, setScrolled] = useState(false);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 8);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    return (
        <div className="flex min-h-screen flex-col">
            <header
                className={cn(
                    'sticky top-0 z-40 transition-all duration-300 md:pt-3',
                    // Immersive pages draw their own mobile top bar over the hero.
                    immersive && 'hidden md:block',
                )}
            >
                <div
                    className={cn(
                        'mx-auto flex h-16 max-w-7xl items-center justify-between px-4 transition-all duration-300 md:h-[3.75rem] md:rounded-full md:border md:px-3 md:pl-5 lg:max-w-[76rem]',
                        scrolled || floatingNav
                            ? 'border-b border-tint/[0.06] bg-canvas/75 backdrop-blur-2xl md:border-tint/10 md:bg-card/70 md:shadow-premium'
                            : 'border-transparent md:border-tint/[0.06] md:bg-tint/[0.03] md:backdrop-blur-xl',
                    )}
                >
                    <Link href="/" aria-label="TUVIO home" className="shrink-0">
                        <Logo className="h-8 md:h-9" />
                    </Link>

                    <nav className="hidden items-center gap-1 md:flex">
                        {nav.map((item) => {
                            const active = path === item.href || (item.href !== '/' && !item.href.includes('#') && path.startsWith(item.href));
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={cn(
                                        'rounded-full px-4 py-2 text-[13px] font-semibold transition-colors',
                                        active ? 'bg-brand-500/10 text-brand-300' : 'text-slate-500 hover:bg-brand-500/[0.07] hover:text-brand-300',
                                    )}
                                >
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="hidden items-center gap-2 md:flex">
                        {auth.user ? (
                            <>
                                <Button href="/my-account/tickets" variant="ghost" size="sm">My tickets</Button>
                                <Button href="/dashboard" size="sm">Dashboard</Button>
                            </>
                        ) : (
                            <>
                                <Button href="/login" variant="ghost" size="sm">Log in</Button>
                                <Button href="/register" size="sm">Get started</Button>
                            </>
                        )}
                    </div>

                    <div className="flex items-center gap-2 md:hidden">
                        {auth.user ? (
                            <Link
                                href="/my-account/tickets"
                                className="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-brand-700 text-sm font-bold text-white ring-2 ring-white/10"
                                aria-label="My tickets"
                            >
                                {auth.user.name.charAt(0).toUpperCase()}
                            </Link>
                        ) : (
                            <Button href="/register" size="sm">Join</Button>
                        )}
                        <button
                            className="grid h-10 w-10 place-items-center rounded-full border border-tint/10 bg-tint/[0.04]"
                            onClick={() => setOpen((v) => !v)}
                            aria-label="Menu"
                            aria-expanded={open}
                        >
                            {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                        </button>
                    </div>
                </div>

                <AnimatePresence>
                    {open && (
                        <motion.div
                            initial={{ opacity: 0, y: -8 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0, y: -8 }}
                            className="glass absolute inset-x-3 top-[4.25rem] rounded-3xl p-2 shadow-premium md:hidden"
                        >
                            {[...nav, { label: 'About', href: '/about' }, { label: 'Contact', href: '/contact' }].map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setOpen(false)}
                                    className="block rounded-2xl px-4 py-3 text-[15px] font-semibold text-slate-700 hover:bg-tint/[0.06]"
                                >
                                    {item.label}
                                </Link>
                            ))}
                            <div className="mx-4 mt-1 flex gap-4 border-t border-tint/[0.06] pt-3 pb-1 text-xs font-semibold text-slate-400">
                                <Link href="/terms" onClick={() => setOpen(false)} className="hover:text-ink">Terms</Link>
                                <Link href="/privacy" onClick={() => setOpen(false)} className="hover:text-ink">Privacy</Link>
                            </div>
                            {!auth.user && (
                                <div className="mt-1 grid grid-cols-2 gap-2 p-2">
                                    <Button href="/login" variant="outline">Log in</Button>
                                    <Button href="/register">Get started</Button>
                                </div>
                            )}
                        </motion.div>
                    )}
                </AnimatePresence>
            </header>

            <main className="flex-1 pb-tabbar md:pb-0">{children}</main>

            <footer className="relative mt-16 hidden overflow-hidden border-t border-tint/[0.06] md:block">
                <div className="pointer-events-none absolute -right-24 -top-32 h-80 w-80 rounded-full bg-brand-500/15 blur-3xl" />
                <div className="container-page relative grid grid-cols-2 gap-8 py-16 md:grid-cols-5">
                    <div className="col-span-2">
                        <Logo variant="tagline" className="h-14" />
                        <p className="mt-4 max-w-xs text-sm text-slate-400">
                            Concerts, conferences, parties and everything in between. Discover, create and sell tickets across Africa and beyond.
                        </p>
                        <p className="mt-6 font-display text-3xl italic text-slate-600">See you out there.</p>
                    </div>
                    <FooterCol title="Platform" links={[['Discover events', '/events'], ['Categories', '/categories'], ['Pricing', '/pricing'], ['Become an organizer', '/register']]} />
                    <FooterCol title="Company" links={[['About', '/about'], ['Contact', '/contact']]} />
                    <FooterCol title="Legal" links={[['Terms', '/terms'], ['Privacy', '/privacy']]} />
                </div>
                <div className="border-t border-tint/[0.06]">
                    <div className="container-page flex items-center justify-between gap-2 py-6 text-sm text-slate-400">
                        <span>© {new Date().getFullYear()} TUVIO. Life Happens Here.</span>
                        <span>Mobile Money · Visa · Mastercard</span>
                    </div>
                </div>
            </footer>

            {!hideTabBar && <MobileTabBar />}
            <Toaster />
        </div>
    );
}

function FooterCol({ title, links }: { title: string; links: [string, string][] }) {
    return (
        <div>
            <h4 className="text-sm font-semibold text-ink">{title}</h4>
            <ul className="mt-4 space-y-2.5 text-sm">
                {links.map(([label, href]) => (
                    <li key={href}>
                        <Link href={href} className="text-slate-400 transition-colors hover:text-ink">
                            {label}
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}
