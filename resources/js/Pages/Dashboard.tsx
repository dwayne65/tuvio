import { Accent, EmptyState, greeting, Panel } from '@/Components/app/Kit';
import { Reveal, RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import { StatCard } from '@/Components/StatCard';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ArrowUpRight, BarChart3, CalendarDays, CalendarPlus, Check, Hourglass, LucideIcon, Plus, QrCode, Rocket, ShieldX, Ticket, TrendingUp, Users, Wallet } from 'lucide-react';

type Props = {
    isOrganizer: boolean;
    organization: { id: number; name: string; verification_status: string; status: string; rejection_reason: string | null; has_subscription: boolean } | null;
    metrics: { events: number; published: number; in_review: number; tickets_sold: number; revenue_minor: number } | null;
    recentEvents: { title: string; slug: string; status: string; sold: number; start_at: string | null }[];
};

const tone: Record<string, 'success' | 'warning' | 'neutral' | 'danger' | 'brand'> = {
    published: 'success', draft: 'neutral', unpublished: 'warning', cancelled: 'danger', in_review: 'brand', rejected: 'danger',
};

/** Shortcuts on the overview; one with a capability is swapped for its fallback when the plan lacks it. */
/** What the dashboard says about each plan: what it includes, and what upgrading adds. */
const planPitch: Record<string, string> = {
    starter: 'One event to try TUVIO. Switch to Professional for free to keep publishing, with analytics, discounts, promoters and a team.',
    professional: 'No monthly fee, every growth tool included. Selling a lot? Enterprise cuts your fee to 3.7%.',
    enterprise: 'The lowest fee on TUVIO, with unlimited events, team seats and financial reports.',
};

const quickActions: { label: string; href: string; icon: LucideIcon; capability?: string; fallback?: { label: string; href: string; icon: LucideIcon } }[] = [
    { label: 'New event', href: '/dashboard/events/create', icon: CalendarPlus },
    { label: 'Scan tickets', href: '/dashboard/check-in', icon: QrCode },
    { label: 'Analytics', href: '/dashboard/analytics', icon: BarChart3, capability: 'advanced_analytics', fallback: { label: 'Attendees', href: '/dashboard/attendees', icon: Users } },
    { label: 'Payouts', href: '/dashboard/payouts', icon: Wallet },
];

export default function Dashboard({ auth, isOrganizer, organization, metrics, recentEvents }: PageProps<Props>) {
    const firstName = auth.user?.name.split(' ')[0] ?? 'there';
    const plan = auth.currentOrganization?.plan ?? null;
    const planCapabilities = plan?.capabilities ?? [];

    if (organization && organization.status !== 'active') {
        const rejected = organization.status === 'rejected';
        return (
            <AppLayout title={<>{greeting()}, <Accent>{firstName}</Accent></>}>
                <Head title="Dashboard" />
                <Reveal>
                    <div className="mx-auto max-w-2xl rounded-[1.75rem] border border-tint/[0.08] bg-card p-7 text-center shadow-[var(--shadow-card)] sm:p-10">
                        <span className={`mx-auto grid h-16 w-16 place-items-center rounded-full ${rejected ? 'bg-rose-500/10 text-rose-500' : 'bg-brand-500/10 text-brand-500'}`}>
                            {rejected ? <ShieldX className="h-8 w-8" /> : <Hourglass className="h-8 w-8" />}
                        </span>
                        <h2 className="mt-5 text-2xl font-extrabold tracking-[-0.03em] text-ink">
                            {rejected ? `${organization.name} was not approved` : organization.status === 'pending' ? `${organization.name} is under review` : `${organization.name} is not active`}
                        </h2>
                        <p className="mx-auto mt-3 max-w-md text-slate-500">
                            {rejected
                                ? organization.rejection_reason ?? 'Our team could not approve this organizer account.'
                                : organization.status === 'pending'
                                  ? 'To keep TUVIO free of spam and fake events, a member of our team checks every new organizer. Your dashboard opens as soon as you are approved, and we will email you.'
                                  : 'This organizer account has been paused. Contact support for help.'}
                        </p>
                        {!rejected && organization.status === 'pending' && (
                            <ol className="mx-auto mt-7 grid max-w-md gap-3 text-left text-sm">
                                <Step done label="Account created" />
                                <Step done label="Organizer details submitted" />
                                <Step label="Approval by the TUVIO team" current />
                                <Step label="Create events and submit them for publishing" />
                            </ol>
                        )}
                        <div className="mt-8 flex flex-wrap justify-center gap-3">
                            <Button href="/contact" variant="outline">Contact support</Button>
                        </div>
                    </div>
                </Reveal>
            </AppLayout>
        );
    }

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

            {plan && (
                <Reveal className="mb-5">
                    <div className="relative isolate flex flex-col items-start justify-between gap-4 overflow-hidden rounded-[1.5rem] bg-gradient-to-r from-brand-500 via-brand-700 to-[#0b1a44] p-5 text-white shadow-premium sm:flex-row sm:items-center sm:p-6">
                        <div className="dot-grid pointer-events-none absolute inset-0 -z-10 opacity-30" />
                        <div>
                            <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-white/60">Your plan</p>
                            <h3 className="mt-1 text-lg font-bold">{plan.name}</h3>
                            <p className="text-sm text-white/75">{planPitch[plan.slug] ?? 'Upgrade any time for more features.'}</p>
                        </div>
                        <Button href="/dashboard/subscription" variant="secondary" className="bg-white text-[#0b0d12]">
                            {plan.slug === 'enterprise' ? 'Manage plan' : plan.slug === 'starter' ? 'Switch to Professional, free' : 'See Enterprise'}
                        </Button>
                    </div>
                </Reveal>
            )}

            {/* Revenue hero + quick actions */}
            <div className="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                <Reveal>
                    <div className="relative h-full overflow-hidden rounded-[1.75rem] bg-gradient-to-br from-[#0b1030] via-[#24115e] to-brand-700 p-6 text-white shadow-premium sm:p-7">
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
                        {quickActions.map((q) => (q.capability && !planCapabilities.includes(q.capability) && q.fallback ? q.fallback : q)).map((a) => (
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
                <RevealItem><StatCard icon={CalendarDays} label="Total events" value={metrics.events} hint={metrics.in_review > 0 ? `${metrics.published} published · ${metrics.in_review} in review` : `${metrics.published} published`} /></RevealItem>
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
                                        <Badge tone={tone[e.status] ?? 'neutral'}>{e.status.replace('_', ' ')}</Badge>
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

function Step({ label, done, current }: { label: string; done?: boolean; current?: boolean }) {
    return (
        <li className={`flex items-center gap-3 rounded-2xl px-4 py-3 ${current ? 'bg-brand-500/10 font-semibold text-ink' : 'bg-tint/[0.03] text-slate-500'}`}>
            <span className={`grid h-6 w-6 shrink-0 place-items-center rounded-full text-xs ${done ? 'bg-emerald-500 text-white' : current ? 'bg-brand-500 text-white' : 'bg-tint/[0.08]'}`}>
                {done ? <Check className="h-3.5 w-3.5" /> : current ? <Hourglass className="h-3.5 w-3.5" /> : null}
            </span>
            {label}
        </li>
    );
}
