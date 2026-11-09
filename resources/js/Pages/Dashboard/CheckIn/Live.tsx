import { EmptyState, Panel } from '@/Components/app/Kit';
import { StatCard } from '@/Components/StatCard';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import { tierMeta } from '@/lib/ticketTiers';
import { PageProps } from '@/types';
import { Head, usePoll } from '@inertiajs/react';
import { Activity, CheckCircle2, Clock, DoorOpen, ScanLine, Settings2, Ticket, Users } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

const BRAND = '#2f7dff';
const AXIS = '#7d849a';
const GRID = 'rgba(127,135,160,0.16)';

interface Stats {
    sold: number;
    checked_in: number;
    remaining: number;
    percent: number;
    scans: Record<string, number>;
    by_type: { name: string; tier: string; sold: number; checked_in: number }[];
    by_gate: { gate: string; checked_in: number }[];
    timeline: { at: string; count: number }[];
    recent: { result: string; attendee: string | null; type: string | null; code: string | null; gate: string | null; scanner: string | null; at: string }[];
    active_scanners: number;
}

const RESULTS: Record<string, { label: string; tone: 'success' | 'warning' | 'danger' }> = {
    valid: { label: 'Admitted', tone: 'success' },
    duplicate: { label: 'Already used', tone: 'warning' },
    invalid: { label: 'Invalid', tone: 'danger' },
    wrong_event: { label: 'Wrong event', tone: 'danger' },
    unpaid: { label: 'Unpaid', tone: 'danger' },
    void: { label: 'Void', tone: 'danger' },
};

const time = (iso: string) => new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

export default function CheckInLive({
    event,
    stats,
    canManage,
    updatedAt,
}: PageProps<{ event: { title: string; slug: string }; stats: Stats; canManage: boolean; updatedAt: string }>) {
    usePoll(5000, { only: ['stats', 'updatedAt'] });

    const base = `/dashboard/check-in/${event.slug}`;
    const refused = Object.entries(stats.scans).filter(([r]) => r !== 'valid').reduce((sum, [, n]) => sum + n, 0);
    const gateMax = Math.max(1, ...stats.by_gate.map((g) => g.checked_in));

    return (
        <AppLayout
            title="Live check-in"
            subtitle={event.title}
            back={{ href: base, label: 'Scanner' }}
            actions={
                <div className="flex gap-2">
                    {canManage && <Button href={`${base}/setup`} variant="outline" size="sm"><Settings2 className="h-4 w-4" /> Setup</Button>}
                    <Button href={base} size="sm"><ScanLine className="h-4 w-4" /> Scanner</Button>
                </div>
            }
        >
            <Head title={`Live · ${event.title}`} />

            <p className="mb-4 inline-flex items-center gap-2 text-xs font-medium text-slate-500" aria-live="polite">
                <span className="relative flex h-2 w-2">
                    <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75" />
                    <span className="relative inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                </span>
                Updates every 5 seconds · last at {time(updatedAt)}
            </p>

            <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <StatCard icon={CheckCircle2} tone="emerald" label="Checked in" value={stats.checked_in.toLocaleString()} hint={`${stats.percent}% of tickets sold`} />
                <StatCard icon={Ticket} label="Still to arrive" value={stats.remaining.toLocaleString()} hint={`${stats.sold.toLocaleString()} tickets sold`} />
                <StatCard icon={Activity} tone="amber" label="Refused scans" value={refused.toLocaleString()} hint="Duplicate, invalid, unpaid or void" />
                <StatCard icon={Users} tone="blue" label="Active scanners" value={stats.active_scanners.toLocaleString()} hint="Scanned in the last 15 min" />
            </div>

            <div className="mt-4 h-2.5 overflow-hidden rounded-full bg-tint/[0.06]" role="progressbar" aria-valuenow={stats.percent} aria-valuemin={0} aria-valuemax={100} aria-label="Attendance">
                <div className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500 transition-[width] duration-700" style={{ width: `${stats.percent}%` }} />
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <Panel className="lg:col-span-2" title="Arrivals" subtitle="Admitted per 15 minutes">
                    {stats.timeline.length === 0 ? (
                        <p className="py-12 text-center text-sm text-slate-500">No one has been admitted yet.</p>
                    ) : (
                        <div className="h-64">
                            <ResponsiveContainer width="100%" height="100%">
                                <BarChart data={stats.timeline} margin={{ top: 4, right: 4, left: -16, bottom: 0 }}>
                                    <CartesianGrid vertical={false} stroke={GRID} />
                                    <XAxis dataKey="at" tickFormatter={time} tick={{ fill: AXIS, fontSize: 11 }} axisLine={false} tickLine={false} minTickGap={24} />
                                    <YAxis allowDecimals={false} tick={{ fill: AXIS, fontSize: 11 }} axisLine={false} tickLine={false} width={44} />
                                    <Tooltip
                                        cursor={{ fill: 'rgba(47,125,255,0.08)' }}
                                        labelFormatter={(at: string) => `${time(at)} – ${time(new Date(new Date(at).getTime() + 15 * 60000).toISOString())}`}
                                        formatter={(value: number) => [value.toLocaleString(), 'Admitted']}
                                        contentStyle={{ borderRadius: 12, border: '1px solid color-mix(in srgb, var(--color-tint) 10%, transparent)', background: 'var(--color-card)', color: 'var(--color-ink)', fontSize: 13 }}
                                    />
                                    <Bar dataKey="count" fill={BRAND} radius={[4, 4, 0, 0]} maxBarSize={28} />
                                </BarChart>
                            </ResponsiveContainer>
                        </div>
                    )}
                </Panel>

                <Panel title="By entrance">
                    {stats.by_gate.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-500">No admissions yet.</p>
                    ) : (
                        <ul className="space-y-3">
                            {stats.by_gate.map((g) => (
                                <li key={g.gate}>
                                    <div className="flex items-center justify-between gap-3 text-sm">
                                        <span className="inline-flex min-w-0 items-center gap-2 font-semibold text-ink"><DoorOpen className="h-4 w-4 shrink-0 text-slate-400" /><span className="truncate">{g.gate}</span></span>
                                        <span className="font-bold tabular-nums text-ink">{g.checked_in.toLocaleString()}</span>
                                    </div>
                                    <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-tint/[0.06]">
                                        <div className="h-full rounded-full bg-brand-500" style={{ width: `${(g.checked_in / gateMax) * 100}%` }} />
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>

            <div className="mt-5 grid gap-5 lg:grid-cols-3">
                <Panel title="By ticket type" subtitle="Arrived of sold">
                    {stats.by_type.length === 0 ? (
                        <p className="py-6 text-center text-sm text-slate-500">No tickets sold yet.</p>
                    ) : (
                        <ul className="space-y-3">
                            {stats.by_type.map((t) => {
                                const pct = t.sold > 0 ? Math.round((t.checked_in / t.sold) * 100) : 0;
                                return (
                                    <li key={t.name}>
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <span className="min-w-0 truncate font-semibold text-ink">{t.name}{tierMeta(t.tier).label.toLowerCase() !== t.name.toLowerCase() && <span className="ml-1.5 text-xs font-medium text-slate-400">{tierMeta(t.tier).label}</span>}</span>
                                            <span className="shrink-0 tabular-nums text-slate-500"><strong className="text-ink">{t.checked_in.toLocaleString()}</strong> / {t.sold.toLocaleString()}</span>
                                        </div>
                                        <div className="mt-1.5 h-1.5 overflow-hidden rounded-full bg-tint/[0.06]">
                                            <div className="h-full rounded-full bg-emerald-500" style={{ width: `${pct}%` }} />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                    {Object.keys(stats.scans).length > 0 && (
                        <div className="mt-5 border-t border-tint/[0.07] pt-4">
                            <p className="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">All scans</p>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {Object.entries(stats.scans).map(([result, n]) => (
                                    <Badge key={result} tone={RESULTS[result]?.tone ?? 'neutral'}>{RESULTS[result]?.label ?? result} · {n.toLocaleString()}</Badge>
                                ))}
                            </div>
                        </div>
                    )}
                </Panel>

                <Panel className="lg:col-span-2" title="Latest scans" bodyClassName="px-0 pb-2 pt-3 sm:px-0">
                    {stats.recent.length === 0 ? (
                        <div className="px-5 pb-3 sm:px-6"><EmptyState icon={ScanLine} title="Waiting for the first scan" body="Scans from every device appear here as they happen." /></div>
                    ) : (
                        <ul className="divide-y divide-tint/[0.07]">
                            {stats.recent.map((s, i) => (
                                <li key={`${s.at}-${i}`} className="flex items-center gap-3 px-5 py-2.5 sm:px-6">
                                    <Badge tone={RESULTS[s.result]?.tone ?? 'neutral'} className="w-[6.5rem] shrink-0 justify-center">{RESULTS[s.result]?.label ?? s.result}</Badge>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-semibold text-ink">{s.attendee ?? (s.code ? 'Guest' : 'Unknown ticket')}</p>
                                        <p className="truncate text-xs text-slate-500">{[s.type, s.code, s.gate, s.scanner && `by ${s.scanner}`].filter(Boolean).join(' · ')}</p>
                                    </div>
                                    <span className="inline-flex shrink-0 items-center gap-1 text-xs tabular-nums text-slate-400"><Clock className="h-3 w-3" />{time(s.at)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </AppLayout>
    );
}
