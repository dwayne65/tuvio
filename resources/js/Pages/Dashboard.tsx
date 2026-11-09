import { Accent, EmptyState, greeting, Panel } from '@/Components/app/Kit';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { StatCard } from '@/Components/StatCard';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, BarChart3, CalendarDays, CalendarPlus, LucideIcon, Plus, QrCode, Rocket, Ticket, TrendingUp, Wallet } from 'lucide-react';

type Props = {
    isOrganizer: boolean;
    organization: { id: number; name: string; verification_status: string; has_subscription: boolean } | null;
    metrics: { events: number; published: number; tickets_sold: number; revenue_minor: number } | null;
    recentEvents: { title: string; slug: string; status: string; sold: number; start_at: string | null }[];
};

const tone: Record<string, 'success' | 'warning' | 'neutral' | 'danger'> = {
    published: 'success', draft: 'neutral', unpublished: 'warning', cancelled: 'danger',
};

const quickActions: { label: string; href: string; icon: LucideIcon }[] = [
    { label: 'New event', href: '/dashboard/events/create', icon: CalendarPlus },
    { label: 'Scan tickets', href: '/dashboard/check-in', icon: QrCode },
    { label: 'Analytics', href: '/dashboard/analytics', icon: BarChart3 },
    { label: 'Payouts', href: '/dashboard/payouts', icon: Wallet },
];

export default function Dashboard({ auth, isOrganizer, organization, metrics, recentEvents }: PageProps<Props>) {
    const firstName = auth.user?.name.split(' ')[0] ?? 'there';

    if (!isOrganizer || !organization || !metrics) {
        return (
            <AppLayout title={<>{greeting()}, <Accent>{firstName}</Accent></>}>
                <Head title="Dashboard" />
                <Reveal>
                    <EmptyState
                        icon={Rocket}
                        title="Start selling tickets"
                        body="Create your organizer profile to publish events, sell tickets and track revenue."
                        action={
                            <Button href="/organizations/new" size="lg">
                                Become an organizer <ArrowRight className="h-4 w-4" />
                            </Button>
                        }
                    />
                </Reveal>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title={<>{greeting()}, <Accent>{firstName}</Accent></>}
            subtitle={`Here’s how ${organization.name} is doing.`}
            actions={
                <Button href="/dashboard/events/create">
                    <Plus className="h-4 w-4" /> Create event
                </Button>
            }
        >
            <Head title="Dashboard" />

            {!organization.has_subscription && (
                <Reveal className="mb-5">
                    <div className="relative isolate flex flex-col items-start justify-between gap-4 overflow-hidden rounded-[1.5rem] bg-gradient-to-r from-brand-500 via-brand-700 to-[#0b1a44] p-5 text-white shadow-premium sm:flex-row sm:items-center sm:p-6">
                        <div className="dot-grid pointer-events-none absolute inset-0 -z-10 opacity-30" />
                        <div>
                            <h3 className="font-bold">Choose a plan to start publishing</h3>
                            <p className="text-sm text-white/75">An active subscription is required before your events go live.</p>
                        </div>
                        <Button href="/dashboard/subscription" variant="secondary" className="bg-white text-[#0b0d12]">View plans</Button>
                    </div>
                </Reveal>
            )}

            {/* Revenue hero + quick actions */}
            <div className="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                <Reveal>
                    <div className="relative h-full overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#10162e] via-[#13244a] to-brand-700 p-6 text-white shadow-premium sm:p-7">
                        <div className="absolute -right-16 -top-20 h-64 w-64 rounded-full bg-brand-400/30 blur-3xl" />
                        <div className="dot-grid pointer-events-none absolute inset-0 opacity-30" />
                        <div className="relative">
                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-white/60">Total revenue</p>
                            <p className="mt-2 text-[2.4rem] font-extrabold leading-none tracking-[-0.04em] sm:text-5xl">{money(metrics.revenue_minor)}</p>
                            <div className="mt-6 flex flex-wrap gap-2">
                                <HeroPill icon={Ticket} label={`${metrics.tickets_sold.toLocaleString()} tickets sold`} />
                                <HeroPill icon={CalendarDays} label={`${metrics.published} live of ${metrics.events}`} />
                            </div>
                            <Link href="/dashboard/analytics" className="mt-6 inline-flex items-center gap-1.5 text-sm font-semibold text-white/80 hover:text-white">
                                See analytics <ArrowUpRight className="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </Reveal>

                <Reveal delay={0.05}>
                    <div className="grid h-full grid-cols-2 gap-3">
                        {quickActions.map((a) => (
                            <Link
                                key={a.href}
                                href={a.href}
                                className="group flex flex-col justify-between gap-6 rounded-[1.5rem] border border-tint/[0.07] bg-card p-4 shadow-[var(--shadow-card)] transition-all hover:-translate-y-0.5 hover:border-brand-400/50"
                            >
                                <span className="grid h-11 w-11 place-items-center rounded-full bg-brand-500/10 text-brand-300 transition-colors group-hover:bg-brand-500 group-hover:text-white">
                                    <a.icon className="h-5 w-5" />
                                </span>
                                <span className="flex items-center justify-between text-sm font-bold text-ink">
                                    {a.label} <ArrowUpRight className="h-4 w-4 text-slate-400 group-hover:text-brand-500" />
                                </span>
                            </Link>
                        ))}
                    </div>
                </Reveal>
            </div>

            <RevealGroup className="mt-5 grid grid-cols-2 gap-3 sm:gap-5 lg:grid-cols-4">
                <RevealItem><StatCard icon={CalendarDays} label="Total events" value={metrics.events} hint={`${metrics.published} published`} /></RevealItem>
                <RevealItem><StatCard icon={TrendingUp} label="Published" value={metrics.published} tone="blue" /></RevealItem>
                <RevealItem><StatCard icon={Ticket} label="Tickets sold" value={metrics.tickets_sold.toLocaleString()} tone="emerald" /></RevealItem>
                <RevealItem><StatCard icon={Wallet} label="Revenue" value={money(metrics.revenue_minor)} tone="amber" /></RevealItem>
            </RevealGroup>

            <Reveal className="mt-5">
                <Panel
                    title="Recent events"
                    subtitle="Your latest drafts and live events"
                    action={<Link href="/dashboard/events" className="text-sm font-semibold text-brand-600 hover:underline">See all</Link>}
                    bodyClassName="pt-3"
                >
                    {recentEvents.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title="No events yet"
                            body="Create your first event to start selling tickets."
                            action={<Button href="/dashboard/events/create" size="sm"><Plus className="h-4 w-4" /> Create event</Button>}
                        />
                    ) : (
                        <ul className="-mx-2 divide-y divide-tint/[0.06]">
                            {recentEvents.map((e) => (
                                <li key={e.slug}>
                                    <Link href={`/dashboard/events/${e.slug}/edit`} className="group flex items-center gap-3 rounded-2xl px-2 py-3.5 transition-colors hover:bg-tint/[0.03]">
                                        <DateBadge iso={e.start_at} />
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-semibold text-ink group-hover:text-brand-600">{e.title}</p>
                                            <p className="text-xs text-slate-400">{e.start_at ? formatDate(e.start_at, { weekday: 'short' }) : 'No date set'} · {e.sold} sold</p>
                                        </div>
                                        <Badge tone={tone[e.status] ?? 'neutral'}>{e.status}</Badge>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </Reveal>
        </AppLayout>
    );
}

function HeroPill({ icon: Icon, label }: { icon: LucideIcon; label: string }) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-semibold backdrop-blur">
            <Icon className="h-3.5 w-3.5" /> {label}
        </span>
    );
}

function DateBadge({ iso }: { iso: string | null }) {
    const d = iso ? new Date(iso) : null;
    return (
        <span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-500/10 text-center ring-1 ring-inset ring-brand-400/20">
            {d ? (
                <span className="leading-none">
                    <span className="block text-[10px] font-bold uppercase tracking-wide text-brand-300">{d.toLocaleDateString('en-US', { month: 'short' })}</span>
                    <span className="block text-lg font-extrabold text-ink">{d.getDate()}</span>
                </span>
            ) : (
                <CalendarDays className="h-5 w-5 text-brand-300" />
            )}
        </span>
    );
}
