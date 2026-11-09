import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Compass, Home, LayoutGrid, LucideIcon, Ticket, UserRound } from 'lucide-react';

interface Tab {
    label: string;
    href: string;
    icon: LucideIcon;
    match: (path: string) => boolean;
}

/** Floating, app-style bottom navigation shown on phones only. */
export function MobileTabBar() {
    const page = usePage<PageProps>();
    const { auth } = page.props;
    const path = page.url.split('?')[0];

    const tabs: Tab[] = [
        { label: 'Home', href: '/', icon: Home, match: (p) => p === '/' },
        { label: 'Explore', href: '/events', icon: Compass, match: (p) => p.startsWith('/events') || p.startsWith('/checkout') },
        { label: 'Tickets', href: auth.user ? '/my-account/tickets' : '/login', icon: Ticket, match: (p) => p.startsWith('/my-account') },
        { label: 'Browse', href: '/categories', icon: LayoutGrid, match: (p) => p.startsWith('/categories') },
        {
            label: auth.user ? 'Me' : 'Sign in',
            href: auth.user ? '/dashboard' : '/login',
            icon: UserRound,
            match: (p) => p.startsWith('/dashboard') || p.startsWith('/settings') || p === '/login' || p === '/register',
        },
    ];

    return (
        <nav
            aria-label="Primary"
            className="fixed inset-x-0 bottom-0 z-50 px-4 pb-[max(0.9rem,env(safe-area-inset-bottom))] md:hidden"
        >
            <div className="glass mx-auto flex max-w-md items-center justify-between rounded-full p-1.5 shadow-premium">
                {tabs.map((tab) => {
                    const active = tab.match(path);
                    return (
                        <Link
                            key={tab.label}
                            href={tab.href}
                            aria-label={tab.label}
                            aria-current={active ? 'page' : undefined}
                            className="relative flex h-12 flex-1 items-center justify-center"
                        >
                            {active && (
                                <motion.span
                                    layoutId="tab-active"
                                    className="absolute inset-y-0 left-1/2 w-12 -translate-x-1/2 rounded-full bg-brand-500 glow-blue"
                                    transition={{ type: 'spring', stiffness: 500, damping: 36 }}
                                />
                            )}
                            <tab.icon
                                className={cn('relative h-[21px] w-[21px] transition-colors', active ? 'text-white' : 'text-slate-400')}
                                strokeWidth={active ? 2.4 : 2}
                            />
                        </Link>
                    );
                })}
            </div>
        </nav>
    );
}
