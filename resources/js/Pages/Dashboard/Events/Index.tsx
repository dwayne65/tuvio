import { DataTable } from '@/Components/app/DataTable';
import { Accent, EmptyState, Pagination } from '@/Components/app/Kit';
import { Reveal } from '@/Components/motion/Reveal';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, CalendarDays, Copy, Plus, Ticket } from 'lucide-react';

type Ev = {
    id: number;
    title: string;
    slug: string;
    status: string;
    start_at: string | null;
    tickets_count: number;
    ticket_types_count: number;
};

type Props = {
    events: { data: Ev[]; links: { url: string | null; label: string; active: boolean }[] };
    canPublish: boolean;
};

const tone: Record<string, 'success' | 'warning' | 'neutral' | 'danger' | 'brand'> = {
    published: 'success',
    draft: 'neutral',
    unpublished: 'warning',
    cancelled: 'danger',
    completed: 'neutral',
    in_review: 'brand',
    rejected: 'danger',
};

function duplicate(e: Ev) {
    router.post(`/dashboard/events/${e.slug}/duplicate`, {}, { preserveScroll: true });
}

export default function EventsIndex({ events, canPublish }: PageProps<Props>) {
    return (
        <AppLayout
            title={<>My <Accent>events</Accent></>}
            subtitle="Create, publish and manage everything you host."
            actions={
                <Button href="/dashboard/events/create">
                    <Plus className="h-4 w-4" /> Create event
                </Button>
            }
        >
            <Head title="My Events" />

            {!canPublish && (
                <Reveal className="mb-4">
                    <div className="flex items-start gap-3 rounded-2xl border border-amber-500/25 bg-amber-500/10 p-4 text-sm">
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                        <p className="text-slate-700">
                            You can build events as drafts, but you’ll need an active plan to publish them.{' '}
                            <Link href="/dashboard/subscription" className="font-semibold text-brand-600 hover:underline">Choose a plan</Link>
                        </p>
                    </div>
                </Reveal>
            )}

            <Reveal>
                <DataTable
                    rows={events.data}
                    rowKey={(e) => e.id}
                    empty={
                        <EmptyState
                            icon={CalendarDays}
                            title="No events yet"
                            body="Create your first event to start selling tickets."
                            action={<Button href="/dashboard/events/create"><Plus className="h-4 w-4" /> Create event</Button>}
                        />
                    }
                    columns={[
                        {
                            header: 'Event',
                            cell: (e) => (
                                <Link href={`/dashboard/events/${e.slug}/edit`} className="font-semibold text-ink hover:text-brand-600">
                                    {e.title}
                                </Link>
                            ),
                        },
                        { header: 'Status', cell: (e) => <Badge tone={tone[e.status] ?? 'neutral'}>{e.status.replace('_', ' ')}</Badge> },
                        { header: 'Date', cell: (e) => <span className="whitespace-nowrap text-slate-500">{e.start_at ? formatDate(e.start_at) : '—'}</span> },
                        { header: 'Sales', cell: (e) => <span className="text-slate-500">{e.tickets_count} sold · {e.ticket_types_count} types</span> },
                        {
                            header: 'Actions',
                            align: 'right',
                            cell: (e) => (
                                <div className="flex justify-end gap-2">
                                    <Button href={`/dashboard/events/${e.slug}/edit`} variant="outline" size="sm">Manage</Button>
                                    <IconButton label={`Duplicate ${e.title}`} onClick={() => duplicate(e)} />
                                </div>
                            ),
                        },
                    ]}
                    mobile={(e) => (
                        <div>
                            <div className="flex items-start justify-between gap-3">
                                <Link href={`/dashboard/events/${e.slug}/edit`} className="min-w-0">
                                    <p className="truncate font-bold text-ink">{e.title}</p>
                                    <p className="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400">
                                        <CalendarDays className="h-3.5 w-3.5" /> {e.start_at ? formatDate(e.start_at, { weekday: 'short' }) : 'No date set'}
                                    </p>
                                </Link>
                                <Badge tone={tone[e.status] ?? 'neutral'}>{e.status.replace('_', ' ')}</Badge>
                            </div>
                            <div className="mt-3 flex items-center justify-between gap-2 border-t border-tint/[0.06] pt-3">
                                <span className="flex items-center gap-1.5 text-sm text-slate-500">
                                    <Ticket className="h-4 w-4 text-brand-300" /> {e.tickets_count} sold · {e.ticket_types_count} types
                                </span>
                                <div className="flex gap-2">
                                    <IconButton label={`Duplicate ${e.title}`} onClick={() => duplicate(e)} />
                                    <Button href={`/dashboard/events/${e.slug}/edit`} size="sm">Manage</Button>
                                </div>
                            </div>
                        </div>
                    )}
                />
            </Reveal>
            <Pagination links={events.links} />
        </AppLayout>
    );
}

function IconButton({ label, onClick }: { label: string; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            title="Duplicate"
            className="grid h-9 w-9 place-items-center rounded-full border border-tint/[0.1] text-slate-500 transition-colors hover:border-brand-400 hover:text-brand-600"
        >
            <Copy className="h-4 w-4" />
        </button>
    );
}
