import { Accent, EmptyState } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import { RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Activity, ArrowRight, QrCode, ScanLine, Settings2 } from 'lucide-react';

type Ev = { id: number; title: string; slug: string; start_at: string | null; access: 'team' | 'scanner' | 'manager'; can_manage: boolean; can_view_dashboard: boolean };

const ACCESS_LABEL = { scanner: 'Gate staff · scanner', manager: 'Gate staff · manager' } as const;

export default function CheckInIndex({ events }: PageProps<{ events: Ev[] }>) {
    return (
        <AppLayout title={<>Check-<Accent>in</Accent></>} subtitle="Pick an event, then scan tickets at the gate.">
            <Head title="Check-in" />
            {events.length === 0 ? (
                <EmptyState icon={QrCode} title="No events to check in" body="Publish an event and it will appear here on the day." />
            ) : (
                <RevealGroup className="grid gap-3 sm:grid-cols-2 sm:gap-4 lg:grid-cols-3">
                    {events.map((e) => {
                        const d = e.start_at ? new Date(e.start_at) : null;
                        return (
                            <RevealItem key={e.id} className="flex flex-col rounded-[1.5rem] border border-tint/[0.07] bg-card shadow-[var(--shadow-card)] transition-colors hover:border-brand-400/50">
                                <Link
                                    href={`/dashboard/check-in/${e.slug}`}
                                    className="group flex flex-1 items-center gap-4 rounded-[1.5rem] p-4 sm:p-5"
                                >
                                    <span className="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-brand-500 text-white glow-blue">
                                        <ScanLine className="h-6 w-6" />
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-bold text-ink">{e.title}</p>
                                        <p className="text-sm text-slate-500">{d ? formatDate(e.start_at!, { weekday: 'short' }) : 'No date'}</p>
                                        <p className="mt-1 text-xs font-semibold text-brand-600">Open scanner</p>
                                    </div>
                                    <ArrowRight className="h-5 w-5 shrink-0 text-slate-400 transition-transform group-hover:translate-x-0.5 group-hover:text-brand-500" />
                                </Link>
                                {(e.access !== 'team' || e.can_view_dashboard || e.can_manage) && (
                                    <div className="flex items-center gap-1 border-t border-tint/[0.07] px-3 py-2 sm:px-4">
                                        {e.access !== 'team' && <Badge tone="brand">{ACCESS_LABEL[e.access]}</Badge>}
                                        <span className="ml-auto flex gap-1">
                                            {e.can_view_dashboard && (
                                                <Link href={`/dashboard/check-in/${e.slug}/live`} className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-tint/[0.06] hover:text-ink">
                                                    <Activity className="h-3.5 w-3.5" /> Live
                                                </Link>
                                            )}
                                            {e.can_manage && (
                                                <Link href={`/dashboard/check-in/${e.slug}/setup`} className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold text-slate-500 hover:bg-tint/[0.06] hover:text-ink">
                                                    <Settings2 className="h-3.5 w-3.5" /> Setup
                                                </Link>
                                            )}
                                        </span>
                                    </div>
                                )}
                            </RevealItem>
                        );
                    })}
                </RevealGroup>
            )}
        </AppLayout>
    );
}
