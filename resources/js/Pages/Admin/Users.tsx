import { DataTable } from '@/Components/app/DataTable';
import { Accent, Avatar, EmptyState, Pagination } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDate } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Search, Users } from 'lucide-react';
import { useState } from 'react';

type U = { id: number; name: string; email: string; is_admin: boolean; verified: boolean; created_at: string };
type Links = { url: string | null; label: string; active: boolean }[];

export default function AdminUsers({ users, filters }: PageProps<{ users: { data: U[]; links: Links }; filters: { q: string } }>) {
    const [q, setQ] = useState(filters.q ?? '');

    return (
        <AdminLayout title={<>Us<Accent>ers</Accent></>} subtitle="Everyone with a TicketFlow account.">
            <Head title="Admin · Users" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/admin/users', { q }, { preserveState: true });
                }}
                className="mb-4 flex max-w-md items-center gap-2 rounded-full border border-tint/10 bg-card p-1.5 shadow-[var(--shadow-card)]"
            >
                <Search className="ml-2.5 h-4 w-4 shrink-0 text-slate-400" />
                <input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search name or email"
                    aria-label="Search users"
                    className="h-9 min-w-0 flex-1 bg-transparent text-sm text-ink placeholder:text-slate-400 focus:outline-none"
                />
                <button type="submit" className="h-9 rounded-full bg-brand-500 px-4 text-sm font-semibold text-white">Search</button>
            </form>

            <DataTable
                rows={users.data}
                rowKey={(u) => u.id}
                empty={<EmptyState icon={Users} title="No users found" body="Try a different search." />}
                columns={[
                    {
                        header: 'User',
                        cell: (u) => (
                            <div className="flex items-center gap-3">
                                <Avatar name={u.name} className="h-9 w-9" />
                                <div className="min-w-0">
                                    <p className="truncate font-semibold text-ink">{u.name}</p>
                                    <p className="truncate text-xs text-slate-400">{u.email}</p>
                                </div>
                            </div>
                        ),
                    },
                    { header: 'Role', cell: (u) => (u.is_admin ? <Badge tone="brand">Admin</Badge> : <Badge tone="neutral">User</Badge>) },
                    { header: 'Verified', cell: (u) => (u.verified ? <Badge tone="success">Verified</Badge> : <Badge tone="warning">Unverified</Badge>) },
                    { header: 'Joined', align: 'right', cell: (u) => <span className="whitespace-nowrap text-slate-500">{formatDate(u.created_at)}</span> },
                ]}
                mobile={(u) => (
                    <div className="flex items-center gap-3">
                        <Avatar name={u.name} />
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-bold text-ink">{u.name}</p>
                            <p className="truncate text-xs text-slate-400">{u.email}</p>
                            <div className="mt-1.5 flex gap-1.5">
                                {u.is_admin && <Badge tone="brand">Admin</Badge>}
                                {u.verified ? <Badge tone="success">Verified</Badge> : <Badge tone="warning">Unverified</Badge>}
                            </div>
                        </div>
                        <span className="shrink-0 text-xs text-slate-400">{formatDate(u.created_at)}</span>
                    </div>
                )}
            />
            <Pagination links={users.links} />
        </AdminLayout>
    );
}
