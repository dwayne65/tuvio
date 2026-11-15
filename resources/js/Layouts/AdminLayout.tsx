import { PageTitle } from '@/Components/app/PageTitle';
import { Toaster } from '@/Components/app/Toaster';
import { Logo } from '@/Components/Logo';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { BarChart3, Bell, Building2, ClipboardCheck, CreditCard, HandCoins, LayoutDashboard, LifeBuoy, LogOut, Menu, Settings, Smartphone, Users, Wallet, X } from 'lucide-react';
import { ReactNode, useState } from 'react';

const nav = [
    { label: 'Overview', href: '/admin', icon: LayoutDashboard },
    { label: 'Approvals', href: '/admin/approvals', icon: ClipboardCheck },
    { label: 'Users', href: '/admin/users', icon: Users },
    { label: 'Organizers', href: '/admin/organizers', icon: Building2 },
    { label: 'Earnings', href: '/admin/earnings', icon: HandCoins },
    { label: 'Transactions', href: '/admin/transactions', icon: CreditCard },
    { label: 'USSD', href: '/admin/ussd', icon: Smartphone },
    { label: 'Payouts', href: '/admin/payouts', icon: Wallet },
    { label: 'Support', href: '/admin/support', icon: LifeBuoy },
    { label: 'Notifications', href: '/notifications', icon: Bell },
    { label: 'Settings', href: '/settings/profile', icon: Settings },
];

export default function AdminLayout({ title, subtitle, actions, children }: { title?: ReactNode; subtitle?: ReactNode; actions?: ReactNode; children: ReactNode }) {
    const page = usePage<PageProps>();
    const { auth, admin, notifications } = page.props;
    const pending = admin?.pending_approvals ?? 0;
    const current = page.url.split('?')[0];
    const [open, setOpen] = useState(false);

    return (
        <div className="min-h-screen">
            <aside className={cn('fixed inset-y-0 left-0 z-50 flex w-[17rem] flex-col overflow-hidden border-r border-tint/[0.06] bg-card/95 backdrop-blur-2xl transition-transform duration-300 lg:translate-x-0', open ? 'translate-x-0' : '-translate-x-full')}>
                <div className="absolute -right-16 -top-20 h-60 w-60 rounded-full bg-brand-500/15 blur-3xl" />
                <div className="relative flex h-[4.5rem] items-center justify-between px-5">
                    <Logo className="h-8" />
                    <span className="ml-2 rounded-full bg-brand-500 px-1.5 py-0.5 text-[10px] font-bold text-white">ADMIN</span>
                    <button className="ml-auto grid h-9 w-9 place-items-center rounded-full bg-tint/[0.06] lg:hidden" onClick={() => setOpen(false)} aria-label="Close"><X className="h-4 w-4" /></button>
                </div>
                <nav className="relative flex flex-1 flex-col gap-1 p-3">
                    <p className="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400">Control center</p>
                    {nav.map((item) => {
                        const active = current === item.href || (item.href !== '/admin' && current.startsWith(item.href));
                        return (
                            <Link key={item.href} href={item.href} onClick={() => setOpen(false)}
                                className={cn('flex items-center gap-3 rounded-full px-4 py-2.5 text-sm font-semibold transition-colors', active ? 'bg-brand-500 text-white glow-blue' : 'text-slate-500 hover:bg-tint/[0.04] hover:text-ink')}>
                                <item.icon className="h-[18px] w-[18px]" /> {item.label}
                                {item.href === '/admin/approvals' && pending > 0 && (
                                    <span className={cn('ml-auto rounded-full px-2 py-0.5 text-[11px] font-bold', active ? 'bg-white/25 text-white' : 'bg-amber-500 text-white')}>{pending}</span>
                                )}
                            </Link>
                        );
                    })}
                </nav>
                <div className="relative m-3 rounded-[1.25rem] border border-tint/[0.07] bg-tint/[0.03] p-3 text-xs text-slate-400">Restricted platform access<br /><span className="font-semibold text-ink">Admin workspace</span></div>
            </aside>

            <div className="lg:pl-[17rem]">
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-tint/[0.06] bg-canvas/75 px-4 backdrop-blur-2xl lg:h-[4.5rem] sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3">
                        <button className="grid h-10 w-10 place-items-center rounded-full border border-tint/[0.08] bg-tint/[0.03] lg:hidden" onClick={() => setOpen(true)} aria-label="Menu"><Menu className="h-5 w-5" /></button>
                        <span className="truncate font-semibold text-ink">Platform admin</span>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="hidden text-sm text-slate-500 sm:inline">{auth.user?.name}</span>
                        <Link href="/notifications" aria-label="Notifications" className="relative grid h-10 w-10 place-items-center rounded-full border border-tint/[0.08] bg-tint/[0.03] text-slate-500 hover:text-ink">
                            <Bell className="h-[18px] w-[18px]" />
                            {(notifications?.unread ?? 0) > 0 && (
                                <span className="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full bg-brand-500 px-1 text-[10px] font-bold text-white">{notifications.unread}</span>
                            )}
                        </Link>
                        <button onClick={() => router.post('/logout')} aria-label="Log out" className="grid h-10 w-10 place-items-center rounded-full border border-tint/[0.08] bg-tint/[0.03] text-slate-500 hover:text-ink">
                            <LogOut className="h-[18px] w-[18px]" />
                        </button>
                    </div>
                </header>
                <main className="px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
                    {title && <PageTitle title={title} subtitle={subtitle} actions={actions} />}
                    {children}
                </main>
            </div>
            <Toaster />
            {open && <div className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" onClick={() => setOpen(false)} />}
        </div>
    );
}
