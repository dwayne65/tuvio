import { Accent, EmptyState } from '@/Components/app/Kit';
import { RevealGroup, RevealItem } from '@/Components/motion/Reveal';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, QrCode, ScanLine } from 'lucide-react';

type Ev = { id: number; title: string; slug: string; start_at: string | null };

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
                            <RevealItem key={e.id}>
                                <Link
                                    href={`/dashboard/check-in/${e.slug}`}
                                    className="group flex items-center gap-4 rounded-[1.5rem] border border-tint/[0.07] bg-card p-4 shadow-[var(--shadow-card)] transition-all hover:-translate-y-0.5 hover:border-brand-400/50 sm:p-5"
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
                            </RevealItem>
                        );
                    })}
                </RevealGroup>
            )}
        </AppLayout>
    );
}
