import { DataTable } from '@/Components/app/DataTable';
import { Accent, EmptyState, Pagination } from '@/Components/app/Kit';
import { Reveal } from '@/Components/motion/Reveal';
import { Badge } from '@/Components/ui/Badge';
import AppLayout from '@/Layouts/AppLayout';
import { formatDateTime, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { ArrowDownLeft, CreditCard, Gift } from 'lucide-react';

type P = { reference: string; purpose: string; provider: string; method: string | null; status: string; amount_minor: number; currency: string; source: string; at: string };

const tone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    successful: 'success', pending: 'warning', initiated: 'neutral', failed: 'danger', refunded: 'neutral',
};

export default function Transactions({ transactions }: PageProps<{ transactions: { data: P[]; links: { url: string | null; label: string; active: boolean }[] } }>) {
    return (
        <AppLayout title={<>Transac<Accent>tions</Accent></>} subtitle="Every payment for your tickets and campaigns.">
            <Head title="Transactions" />
            <Reveal>
                <DataTable
                    rows={transactions.data}
                    rowKey={(p) => p.reference}
                    empty={<EmptyState icon={CreditCard} title="No transactions yet" body="Payments will appear here as soon as someone buys a ticket." />}
                    columns={[
                        {
                            header: 'Payment',
                            cell: (p) => (
                                <div className="flex items-center gap-3">
                                    <PurposeIcon purpose={p.purpose} />
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-ink">{p.source}</p>
                                        <p className="font-mono text-xs text-slate-400">{p.reference}</p>
                                    </div>
                                </div>
                            ),
                        },
                        { header: 'Purpose', cell: (p) => <span className="capitalize text-slate-600">{p.purpose.replace('_', ' ')}</span> },
                        { header: 'Method', cell: (p) => <span className="capitalize text-slate-600">{p.method?.replace('_', ' ') ?? '—'}</span> },
                        { header: 'Status', cell: (p) => <Badge tone={tone[p.status] ?? 'neutral'}>{p.status}</Badge> },
                        { header: 'Date', cell: (p) => <span className="whitespace-nowrap text-slate-500">{formatDateTime(p.at)}</span> },
                        { header: 'Amount', align: 'right', cell: (p) => <span className="whitespace-nowrap font-bold text-ink">{money(p.amount_minor, p.currency)}</span> },
                    ]}
                    mobile={(p) => (
                        <div className="flex items-center gap-3">
                            <PurposeIcon purpose={p.purpose} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-bold text-ink">{p.source}</p>
                                <p className="truncate text-xs text-slate-400">{formatDateTime(p.at)} · {p.method?.replace('_', ' ') ?? p.provider}</p>
                            </div>
                            <div className="flex shrink-0 flex-col items-end gap-1">
                                <span className="text-sm font-bold text-ink">{money(p.amount_minor, p.currency)}</span>
                                <Badge tone={tone[p.status] ?? 'neutral'}>{p.status}</Badge>
                            </div>
                        </div>
                    )}
                />
            </Reveal>
            <Pagination links={transactions.links} />
        </AppLayout>
    );
}

function PurposeIcon({ purpose }: { purpose: string }) {
    const Icon = purpose === 'contribution' ? Gift : ArrowDownLeft;
    return (
        <span className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald-500/10 text-emerald-600">
            <Icon className="h-[18px] w-[18px]" />
        </span>
    );
}
