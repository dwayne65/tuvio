import { DataTable } from '@/Components/app/DataTable';
import { Accent, EmptyState, Pagination } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDateTime, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { CreditCard } from 'lucide-react';

type P = { reference: string; purpose: string; provider: string; status: string; amount_minor: number; currency: string; at: string };
type Links = { url: string | null; label: string; active: boolean }[];

const tone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    successful: 'success', pending: 'warning', initiated: 'neutral', failed: 'danger', refunded: 'neutral',
};

export default function AdminTransactions({ payments }: PageProps<{ payments: { data: P[]; links: Links } }>) {
    return (
        <AdminLayout title={<>Transac<Accent>tions</Accent></>} subtitle="All payments across the platform.">
            <Head title="Admin · Transactions" />
            <DataTable
                rows={payments.data}
                rowKey={(p) => p.reference}
                empty={<EmptyState icon={CreditCard} title="No transactions yet" />}
                columns={[
                    { header: 'Reference', cell: (p) => <span className="font-mono text-xs text-slate-500">{p.reference}</span> },
                    { header: 'Purpose', cell: (p) => <span className="capitalize text-slate-600">{p.purpose.replace('_', ' ')}</span> },
                    { header: 'Provider', cell: (p) => <span className="capitalize text-slate-600">{p.provider}</span> },
                    { header: 'Status', cell: (p) => <Badge tone={tone[p.status] ?? 'neutral'}>{p.status}</Badge> },
                    { header: 'Date', cell: (p) => <span className="whitespace-nowrap text-slate-500">{formatDateTime(p.at)}</span> },
                    { header: 'Amount', align: 'right', cell: (p) => <span className="whitespace-nowrap font-bold text-ink">{money(p.amount_minor, p.currency)}</span> },
                ]}
                mobile={(p) => (
                    <div className="flex items-center gap-3">
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-bold capitalize text-ink">{p.purpose.replace('_', ' ')} · {p.provider}</p>
                            <p className="truncate font-mono text-xs text-slate-400">{p.reference}</p>
                            <p className="text-xs text-slate-400">{formatDateTime(p.at)}</p>
                        </div>
                        <div className="flex shrink-0 flex-col items-end gap-1">
                            <span className="text-sm font-bold text-ink">{money(p.amount_minor, p.currency)}</span>
                            <Badge tone={tone[p.status] ?? 'neutral'}>{p.status}</Badge>
                        </div>
                    </div>
                )}
            />
            <Pagination links={payments.links} />
        </AdminLayout>
    );
}
