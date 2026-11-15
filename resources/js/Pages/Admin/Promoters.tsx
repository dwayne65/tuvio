import { DataTable } from '@/Components/app/DataTable';
import { RemoveWithPassword } from '@/Components/app/RemoveWithPassword';
import { Accent, EmptyState, Pagination } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Megaphone, Search } from 'lucide-react';
import { useState } from 'react';

type P = {
    id: number;
    name: string;
    email: string | null;
    code: string;
    organization: string | null;
    event: string | null;
    commission_percent: number;
    clicks: number;
    orders: number;
    revenue_minor: number;
    is_active: boolean;
};
type Links = { url: string | null; label: string; active: boolean }[];

export default function AdminPromoters({ promoters, filters }: PageProps<{ promoters: { data: P[]; links: Links }; filters: { q: string } }>) {
    const [q, setQ] = useState(filters.q ?? '');

    return (
        <AdminLayout title={<>Pro<Accent>moters</Accent></>} subtitle="Every promoter link across all organizers. Pause or remove any of them.">
            <Head title="Admin · Promoters" />
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get('/admin/promoters', { q }, { preserveState: true });
                }}
                className="mb-4 flex max-w-md items-center gap-2 rounded-full border border-tint/10 bg-card p-1.5 shadow-[var(--shadow-card)]"
            >
                <Search className="ml-2.5 h-4 w-4 shrink-0 text-slate-400" />
                <input
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    placeholder="Search name or code"
                    aria-label="Search promoters"
                    className="h-9 min-w-0 flex-1 bg-transparent text-sm text-ink placeholder:text-slate-400 focus:outline-none"
                />
                <button type="submit" className="h-9 rounded-full bg-brand-500 px-4 text-sm font-semibold text-white">Search</button>
            </form>

            <DataTable
                rows={promoters.data}
                rowKey={(p) => p.id}
                empty={<EmptyState icon={Megaphone} title="No promoters yet" body="Organizers add promoters from their dashboard." />}
                columns={[
                    {
                        header: 'Promoter',
                        cell: (p) => (
                            <div className="min-w-0">
                                <p className="truncate font-semibold text-ink">{p.name}</p>
                                <p className="truncate text-xs text-slate-400">{p.code} · {p.organization ?? '—'}{p.event ? ` · ${p.event}` : ''}</p>
                            </div>
                        ),
                    },
                    { header: 'Commission', align: 'right', cell: (p) => <span className="tabular-nums">{p.commission_percent}%</span> },
                    { header: 'Orders', align: 'right', cell: (p) => <span className="tabular-nums">{p.orders}</span> },
                    { header: 'Revenue', align: 'right', cell: (p) => <span className="whitespace-nowrap tabular-nums">{money(p.revenue_minor)}</span> },
                    { header: 'Status', cell: (p) => (p.is_active ? <Badge tone="success">Active</Badge> : <Badge tone="warning">Paused</Badge>) },
                    {
                        header: '',
                        align: 'right',
                        cell: (p) => (
                            <div className="flex flex-wrap justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => router.post(`/admin/promoters/${p.id}/toggle`, {}, { preserveScroll: true })}
                                    className="rounded-full border border-tint/10 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-tint/[0.05]"
                                >
                                    {p.is_active ? 'Pause' : 'Activate'}
                                </button>
                                <RemoveWithPassword
                                    url={`/admin/promoters/${p.id}`}
                                    label="Remove"
                                    question={`Remove promoter ${p.code}? Past sales keep their revenue but lose this attribution. Enter your password to continue.`}
                                />
                            </div>
                        ),
                    },
                ]}
                mobile={(p) => (
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-bold text-ink">{p.name}</p>
                        <p className="truncate text-xs text-slate-400">{p.code} · {p.organization ?? '—'}</p>
                        <p className="mt-1 text-xs text-slate-500">{p.orders} orders · {money(p.revenue_minor)}</p>
                    </div>
                )}
            />
            <Pagination links={promoters.links} />
        </AdminLayout>
    );
}
