import { RemoveWithPassword } from '@/Components/app/RemoveWithPassword';
import { DataTable } from '@/Components/app/DataTable';
import { Accent, EmptyState, Pagination } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { CalendarDays, Search } from 'lucide-react';
import { useState } from 'react';

type E = {
    id: number;
    title: string;
    slug: string;
    organization: string | null;
    status: string;
    start_at: string | null;
    currency: string;
    tickets_sold: number;
    paid_orders: number;
    revenue_minor: number;
    removed_at: string | null;
};
type Links = { url: string | null; label: string; active: boolean }[];

const statusTone: Record<string, 'success' | 'warning' | 'neutral' | 'danger' | 'brand'> = {
    published: 'success',
    in_review: 'warning',
    draft: 'neutral',
    cancelled: 'danger',
};

export default function AdminEvents({ events, filters }: PageProps<{ events: { data: E[]; links: Links }; filters: { q: string } }>) {
    const [q, setQ] = useState(filters.q ?? '');

    return (
        <AdminLayout title={<>Ev<Accent>ents</Accent></>} subtitle="Every event on TUVIO, live or not. Removing one hides it from the site; sold tickets stay on record.">
            <Head title="Admin · Events" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/admin/events', { q }, { preserveState: true });
                }}
                className="mb-4 flex max-w-md items-center gap-2 rounded-full border border-tint/10 bg-card p-1.5 shadow-[var(--shadow-card)]"
            >
                <Search className="ml-2.5 h-4 w-4 shrink-0 text-slate-400" />
                <input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search event title"
                    aria-label="Search events"
                    className="h-9 min-w-0 flex-1 bg-transparent text-sm text-ink placeholder:text-slate-400 focus:outline-none"
                />
                <button type="submit" className="h-9 rounded-full bg-brand-500 px-4 text-sm font-semibold text-white">Search</button>
            </form>

            <DataTable
                rows={events.data}
                rowKey={(e) => e.id}
                empty={<EmptyState icon={CalendarDays} title="No events found" body="Try a different search." />}
                columns={[
                    {
                        header: 'Event',
                        cell: (e) => (
                            <div className="min-w-0">
                                <p className="truncate font-semibold text-ink">{e.title}</p>
                                <p className="truncate text-xs text-slate-400">{e.organization ?? 'No organizer'}</p>
                            </div>
                        ),
                    },
                    {
                        header: 'Status',
                        cell: (e) =>
                            e.removed_at ? <Badge tone="danger">Removed</Badge> : <Badge tone={statusTone[e.status] ?? 'neutral'}>{e.status.replace('_', ' ')}</Badge>,
                    },
                    { header: 'Starts', cell: (e) => <span className="whitespace-nowrap text-slate-500">{e.start_at ? formatDate(e.start_at) : '—'}</span> },
                    { header: 'Tickets sold', align: 'right', cell: (e) => <span className="tabular-nums">{e.tickets_sold}</span> },
                    { header: 'Revenue', align: 'right', cell: (e) => <span className="whitespace-nowrap tabular-nums">{money(e.revenue_minor, e.currency)}</span> },
                    {
                        header: '',
                        align: 'right',
                        cell: (e) => (
                            <RemoveWithPassword
                                url={`/admin/events/${e.slug}`}
                                label="Remove event"
                                question={
                                    e.tickets_sold > 0
                                        ? `This event has ${e.tickets_sold} tickets sold. Removing it does not refund buyers. Enter your password to continue.`
                                        : 'Remove this event from TUVIO? Enter your password to continue.'
                                }
                                disabled={e.removed_at !== null}
                            />
                        ),
                    },
                ]}
                mobile={(e) => (
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-bold text-ink">{e.title}</p>
                        <p className="truncate text-xs text-slate-400">{e.organization ?? 'No organizer'}</p>
                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                            {e.removed_at ? <Badge tone="danger">Removed</Badge> : <Badge tone={statusTone[e.status] ?? 'neutral'}>{e.status.replace('_', ' ')}</Badge>}
                            <Badge tone="neutral">{e.tickets_sold} sold</Badge>
                        </div>
                        <div className="mt-2">
                            <RemoveWithPassword
                                url={`/admin/events/${e.slug}`}
                                label="Remove event"
                                question="Remove this event? Enter your password to continue."
                                disabled={e.removed_at !== null}
                            />
                        </div>
                    </div>
                )}
            />
            <Pagination links={events.links} />
        </AdminLayout>
    );
}
