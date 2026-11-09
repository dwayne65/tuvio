import { DataTable } from '@/Components/app/DataTable';
import { Accent, Avatar, EmptyState, Pagination } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Building2, CalendarDays } from 'lucide-react';

type O = { id: number; name: string; slug: string; status: string; verification_status: string; events_count: number };
type Links = { url: string | null; label: string; active: boolean }[];

export default function AdminOrganizers({ organizations }: PageProps<{ organizations: { data: O[]; links: Links } }>) {
    return (
        <AdminLayout title={<>Organ<Accent>izers</Accent></>} subtitle="Every organization selling on the platform.">
            <Head title="Admin · Organizers" />
            <DataTable
                rows={organizations.data}
                rowKey={(o) => o.id}
                empty={<EmptyState icon={Building2} title="No organizers yet" />}
                columns={[
                    {
                        header: 'Organization',
                        cell: (o) => (
                            <div className="flex items-center gap-3">
                                <Avatar name={o.name} className="h-9 w-9" />
                                <span className="font-semibold text-ink">{o.name}</span>
                            </div>
                        ),
                    },
                    { header: 'Status', cell: (o) => <Badge tone={o.status === 'active' ? 'success' : 'warning'}>{o.status}</Badge> },
                    { header: 'Verification', cell: (o) => <Badge tone={o.verification_status === 'verified' ? 'success' : 'neutral'}>{o.verification_status}</Badge> },
                    { header: 'Events', align: 'right', cell: (o) => <span className="font-semibold text-ink">{o.events_count}</span> },
                ]}
                mobile={(o) => (
                    <div className="flex items-center gap-3">
                        <Avatar name={o.name} />
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-bold text-ink">{o.name}</p>
                            <div className="mt-1.5 flex flex-wrap gap-1.5">
                                <Badge tone={o.status === 'active' ? 'success' : 'warning'}>{o.status}</Badge>
                                <Badge tone={o.verification_status === 'verified' ? 'success' : 'neutral'}>{o.verification_status}</Badge>
                            </div>
                        </div>
                        <span className="flex shrink-0 items-center gap-1 text-sm font-semibold text-slate-500">
                            <CalendarDays className="h-4 w-4" /> {o.events_count}
                        </span>
                    </div>
                )}
            />
            <Pagination links={organizations.links} />
        </AdminLayout>
    );
}
