import { DataTable } from '@/Components/app/DataTable';
import { Accent, Avatar, EmptyState, Pagination } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check, Wallet } from 'lucide-react';

type P = { id: number; reference: string; organization: string; amount_minor: number; currency: string; status: string; requested_at: string | null };
type Links = { url: string | null; label: string; active: boolean }[];

const tone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    paid: 'success', requested: 'warning', pending: 'warning', processing: 'warning', failed: 'danger', cancelled: 'neutral',
};

function approve(p: P) {
    if (window.confirm(`Mark ${p.reference} (${money(p.amount_minor, p.currency)} to ${p.organization}) as paid?`)) {
        router.post(`/admin/payouts/${p.id}/approve`, {}, { preserveScroll: true });
    }
}

export default function AdminPayouts({ payouts }: PageProps<{ payouts: { data: P[]; links: Links } }>) {
    return (
        <AdminLayout title={<>Pay<Accent>outs</Accent></>} subtitle="Organizer withdrawal requests awaiting settlement.">
            <Head title="Admin · Payouts" />
            <DataTable
                rows={payouts.data}
                rowKey={(p) => p.id}
                empty={<EmptyState icon={Wallet} title="No payouts requested" />}
                columns={[
                    {
                        header: 'Organizer',
                        cell: (p) => (
                            <div className="flex items-center gap-3">
                                <Avatar name={p.organization} className="h-9 w-9" />
                                <div className="min-w-0">
                                    <p className="truncate font-semibold text-ink">{p.organization}</p>
                                    <p className="font-mono text-xs text-slate-400">{p.reference}</p>
                                </div>
                            </div>
                        ),
                    },
                    { header: 'Amount', cell: (p) => <span className="whitespace-nowrap font-bold text-ink">{money(p.amount_minor, p.currency)}</span> },
                    { header: 'Status', cell: (p) => <Badge tone={tone[p.status] ?? 'neutral'}>{p.status}</Badge> },
                    { header: 'Requested', cell: (p) => <span className="whitespace-nowrap text-slate-500">{p.requested_at ? formatDate(p.requested_at) : '—'}</span> },
                    {
                        header: 'Actions',
                        align: 'right',
                        cell: (p) => p.status !== 'paid' && <Button size="sm" onClick={() => approve(p)}><Check className="h-4 w-4" /> Mark paid</Button>,
                    },
                ]}
                mobile={(p) => (
                    <div>
                        <div className="flex items-center gap-3">
                            <Avatar name={p.organization} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-bold text-ink">{p.organization}</p>
                                <p className="truncate font-mono text-xs text-slate-400">{p.reference}</p>
                            </div>
                            <Badge tone={tone[p.status] ?? 'neutral'}>{p.status}</Badge>
                        </div>
                        <div className="mt-3 flex items-center justify-between gap-2 border-t border-tint/[0.06] pt-3">
                            <div>
                                <p className="text-lg font-extrabold text-ink">{money(p.amount_minor, p.currency)}</p>
                                <p className="text-xs text-slate-400">{p.requested_at ? `Requested ${formatDate(p.requested_at)}` : ''}</p>
                            </div>
                            {p.status !== 'paid' && <Button size="sm" onClick={() => approve(p)}><Check className="h-4 w-4" /> Mark paid</Button>}
                        </div>
                    </div>
                )}
            />
            <Pagination links={payouts.links} />
        </AdminLayout>
    );
}
