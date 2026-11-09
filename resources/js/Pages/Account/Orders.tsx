import { Badge } from '@/Components/ui/Badge';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Receipt } from 'lucide-react';

interface O {
    reference: string;
    status: string;
    total_minor: number;
    currency: string;
    created_at: string;
    event: { title: string; slug: string };
    tickets_count: number;
}

const tone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    paid: 'success', awaiting_payment: 'warning', failed: 'danger', expired: 'neutral', refunded: 'neutral',
};

export default function Orders({ orders }: PageProps<{ orders: { data: O[] } }>) {
    return (
        <PublicLayout>
            <Head title="Orders" />
            <div className="container-page max-w-3xl pt-3 md:pt-12">
                <Link href="/my-account/tickets" className="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-ink">
                    <ChevronLeft className="h-4 w-4" /> My tickets
                </Link>
                <h1 className="mt-3 text-[2rem] font-extrabold tracking-[-0.045em] text-ink md:text-4xl">
                    Purchase <span className="font-display font-normal italic tracking-normal text-gradient">history</span>
                </h1>

                <div className="mt-6 space-y-3">
                    {orders.data.length === 0 && (
                        <div className="flex flex-col items-center rounded-[2rem] border border-dashed border-tint/10 bg-card/60 px-6 py-16 text-center">
                            <Receipt className="h-8 w-8 text-slate-400" />
                            <p className="mt-3 text-slate-500">No orders yet.</p>
                        </div>
                    )}
                    {orders.data.map((o) => (
                        <Link
                            key={o.reference}
                            href={`/events/${o.event.slug}`}
                            className="flex items-center gap-4 rounded-[1.4rem] border border-tint/[0.07] bg-card/80 p-4 transition-colors hover:border-tint/15"
                        >
                            <span className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-brand-500/15 text-brand-300">
                                <Receipt className="h-5 w-5" />
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-semibold text-ink">{o.event.title}</p>
                                <p className="mt-0.5 truncate text-xs text-slate-400">
                                    {formatDate(o.created_at)} · {o.tickets_count} {o.tickets_count === 1 ? 'ticket' : 'tickets'} · <span className="font-mono">{o.reference}</span>
                                </p>
                            </div>
                            <div className="flex shrink-0 flex-col items-end gap-1">
                                <span className="text-sm font-bold text-ink">{money(o.total_minor, o.currency)}</span>
                                <Badge tone={tone[o.status] ?? 'neutral'}>{o.status.replace('_', ' ')}</Badge>
                            </div>
                            <ChevronRight className="hidden h-4 w-4 text-slate-400 sm:block" />
                        </Link>
                    ))}
                </div>
            </div>
        </PublicLayout>
    );
}
