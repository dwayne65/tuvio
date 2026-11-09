import { PageTitle } from '@/Components/app/PageTitle';
import { Toaster } from '@/Components/app/Toaster';
import { Logo } from '@/Components/Logo';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import {
    BarChart3, Bell, CalendarDays, CreditCard, Gift, Home, LayoutDashboard, LogOut, LucideIcon, Menu, QrCode,
    Settings, Sparkles, Ticket, Users, Wallet, X,
} from 'lucide-react';
import { ReactNode, useState } from 'react';

const navItems = [
    { label: 'Overview', href: '/dashboard', icon: LayoutDashboard },
    { label: 'My Events', href: '/dashboard/events', icon: CalendarDays },
    { label: 'Tickets', href: '/dashboard/tickets', icon: Ticket },
    { label: 'Attendees', href: '/dashboard/attendees', icon: Users },
    { label: 'Check-in', href: '/dashboard/check-in', icon: QrCode },
    { label: 'Contributions', href: '/dashboard/contributions', icon: Gift },
    { label: 'Transactions', href: '/dashboard/transactions', icon: CreditCard },
    { label: 'Payouts', href: '/dashboard/payouts', icon: Wallet },
    { label: 'Analytics', href: '/dashboard/analytics', icon: BarChart3 },
    { label: 'Subscription', href: '/dashboard/subscription', icon: CreditCard },
    { label: 'Settings', href: '/settings/profile', icon: Settings },
];

function isActive(current: string, href: string): boolean {
    return current === href || (href !== '/dashboard' && current.startsWith(href));
}

export default function AppLayout({
    title,
    subtitle,
    actions,
    back,
    children,
}: {
    title?: ReactNode;
    subtitle?: ReactNode;
    actions?: ReactNode;
    back?: { href: string; label: string };
    children: ReactNode;
}) {
    const page = usePage<PageProps>();
    const { auth, notifications } = page.props;
    const current = page.url.split('?')[0];
    const [open, setOpen] = useState(false);

    return (
        <div className="min-h-screen">
            {/* Sidebar (drawer on phones) */}
            <aside
                className={cn(
                    'fixed inset-y-0 left-0 z-[60] flex w-[17rem] flex-col overflow-hidden border-r border-tint/[0.06] bg-card/95 backdrop-blur-2xl transition-transform duration-300 lg:z-40 lg:translate-x-0',
                    open ? 'translate-x-0' : '-translate-x-full',
                )}
            >
                <div className="absolute -right-20 -top-24 h-64 w-64 rounded-full bg-brand-500/15 blur-3xl" />
                <div className="relative flex h-[4.5rem] items-center justify-between px-5">
                    <Link href="/dashboard">
                        <Logo />
                    </Link>
                    <button className="grid h-9 w-9 place-items-center rounded-full bg-tint/[0.06] lg:hidden" onClick={() => setOpen(false)} aria-label="Close menu">
                        <X className="h-4 w-4" />
                    </button>
                </div>
                <div className="relative flex-1 overflow-y-auto p-3">
                    <p className="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Workspace</p>
                    <nav className="flex flex-col gap-0.5">
                        {navItems.map((item) => {
                            const active = isActive(current, item.href);
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    onClick={() => setOpen(false)}
                                    className={cn(
                                        'relative flex items-center gap-3 rounded-full px-4 py-2.5 text-sm font-semibold transition-colors',
                                        active ? 'text-white' : 'text-slate-500 hover:bg-tint/[0.04] hover:text-ink',
                                    )}
                                >
                                    {active && (
                                        <motion.span
                                            layoutId="nav-active"
                                            className="absolute inset-0 rounded-full bg-brand-500 glow-blue"
                                            transition={{ type: 'spring', stiffness: 400, damping: 32 }}
                                        />
                                    )}
                                    <item.icon className="relative h-[18px] w-[18px]" />
                                    <span className="relative">{item.label}</span>
                                </Link>
                            );
                        })}
                    </nav>
                    <Link href="/" className="mt-4 flex items-center gap-3 rounded-full px-4 py-2.5 text-sm font-semibold text-slate-500 hover:text-ink">
                        <Home className="h-[18px] w-[18px]" /> Back to site
                    </Link>
                </div>
                <div className="relative m-3 flex items-center gap-2.5 rounded-[1.25rem] border border-tint/[0.07] bg-tint/[0.03] p-3">
                    <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-brand-700 text-sm font-bold text-white">
                        {auth.user?.name?.charAt(0).toUpperCase()}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-semibold text-ink">{auth.user?.name}</p>
                        <p className="truncate text-[11px] text-slate-400">{auth.user?.email}</p>
                    </div>
                    <button onClick={() => router.post('/logout')} className="grid h-8 w-8 place-items-center rounded-full text-slate-400 hover:bg-tint/[0.06] hover:text-ink" aria-label="Log out">
                        <LogOut className="h-4 w-4" />
                    </button>
                </div>
            </aside>

            {/* Main column */}
            <div className="lg:pl-[17rem]">
                <header className="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-tint/[0.06] bg-canvas/75 px-4 backdrop-blur-2xl sm:px-6 lg:h-[4.5rem] lg:px-8">
                    <div className="flex min-w-0 items-center gap-3">
                        <Link href="/dashboard" className="lg:hidden" aria-label="Dashboard">
                            <Logo className="[&>span:last-child]:hidden sm:[&>span:last-child]:inline" />
                        </Link>
                        {auth.currentOrganization && (
                            <div className="flex min-w-0 items-center gap-2 rounded-full border border-tint/[0.08] bg-tint/[0.03] px-3 py-1.5">
                                <Sparkles className="h-3.5 w-3.5 shrink-0 text-brand-300" />
                                <span className="truncate text-sm font-semibold text-ink">{auth.currentOrganization.name}</span>
                            </div>
                        )}
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href="/notifications"
                            className="relative grid h-10 w-10 place-items-center rounded-full border border-tint/[0.08] bg-tint/[0.03] text-slate-600 hover:text-ink"
                            aria-label="Notifications"
                        >
                            <Bell className="h-[18px] w-[18px]" />
                            {(notifications?.unread ?? 0) > 0 && (
                                <span className="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-brand-500 px-1 text-[10px] font-bold text-white">
                                    {notifications.unread}
                                </span>
                            )}
                        </Link>
                        <div className="hidden grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-brand-700 text-sm font-bold text-white ring-2 ring-white/10 sm:grid">
                            {auth.user?.name?.charAt(0).toUpperCase()}
                        </div>
                    </div>
                </header>

                <main className="px-4 pb-tabbar pt-6 sm:px-6 lg:px-8 lg:pb-10 lg:pt-8">
                    {title && <PageTitle title={title} subtitle={subtitle} actions={actions} back={back} />}
                    {children}
                </main>
            </div>

            <Toaster />

            {/* Phone tab bar */}
            <nav aria-label="Dashboard" className="fixed inset-x-0 bottom-0 z-50 px-4 pb-[max(0.9rem,env(safe-area-inset-bottom))] lg:hidden">
                <div className="glass mx-auto flex max-w-md items-center justify-between rounded-full p-1.5 shadow-premium">
                    <TabLink href="/dashboard" icon={LayoutDashboard} label="Overview" active={current === '/dashboard'} />
                    <TabLink href="/dashboard/events" icon={CalendarDays} label="Events" active={isActive(current, '/dashboard/events')} />
                    <TabLink href="/dashboard/check-in" icon={QrCode} label="Scan" active={isActive(current, '/dashboard/check-in')} />
                    <TabLink href="/dashboard/tickets" icon={Ticket} label="Tickets" active={isActive(current, '/dashboard/tickets')} />
                    <button onClick={() => setOpen(true)} aria-label="More" className="relative flex h-12 flex-1 items-center justify-center text-slate-400">
                        <Menu className="h-[21px] w-[21px]" />
                    </button>
                </div>
            </nav>

            <AnimatePresence>
                {open && (
                    <motion.div
                        className="fixed inset-0 z-[55] bg-black/60 backdrop-blur-sm lg:hidden"
                        onClick={() => setOpen(false)}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                    />
                )}
            </AnimatePresence>
        </div>
    );
}

function TabLink({ href, icon: Icon, label, active }: { href: string; icon: LucideIcon; label: string; active: boolean }) {
    return (
        <Link href={href} aria-label={label} aria-current={active ? 'page' : undefined} className="relative flex h-12 flex-1 items-center justify-center">
            {active && (
                <motion.span
                    layoutId="dash-tab-active"
                    className="absolute inset-y-0 left-1/2 w-12 -translate-x-1/2 rounded-full bg-brand-500 glow-blue"
                    transition={{ type: 'spring', stiffness: 500, damping: 36 }}
                />
            )}
            <Icon className={cn('relative h-[21px] w-[21px]', active ? 'text-white' : 'text-slate-400')} strokeWidth={active ? 2.4 : 2} />
        </Link>
    );
}
