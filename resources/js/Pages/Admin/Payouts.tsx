import { DataTable } from '@/Components/app/DataTable';
import { Accent, Avatar, EmptyState, Pagination } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Field, Input, Textarea } from '@/Components/ui/Input';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, RefreshCw, Send, Wallet, X } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

type Account = { type: string; provider: string | null; account_name: string | null; account_number: string | null; bank_code: string | null };
type P = {
    id: number;
    reference: string;
    organization: string;
    amount_minor: number;
    currency: string;
    status: string;
    provider: string | null;
    provider_ref: string | null;
    notes: string | null;
    automatic: boolean;
    account: Account | null;
    requested_at: string | null;
    processed_at: string | null;
};
type Links = { url: string | null; label: string; active: boolean }[];

const tone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    paid: 'success', requested: 'warning', pending: 'warning', processing: 'warning', failed: 'danger', cancelled: 'neutral',
};

function Destination({ account }: { account: Account | null }) {
    if (!account) {
        return <span className="text-rose-400">No payout account</span>;
    }

    return (
        <div className="min-w-0">
            <p className="truncate text-sm font-semibold text-ink">{account.account_name}</p>
            <p className="truncate text-xs text-slate-400">
                {account.provider ?? account.type.replace('_', ' ')} · <span className="font-mono">{account.account_number}</span>
                {account.bank_code && <> · {account.bank_code}</>}
            </p>
        </div>
    );
}

function Actions({ p, onApprove, onReject }: { p: P; onApprove: () => void; onReject: () => void }) {
    if (p.status === 'requested') {
        return (
            <div className="flex justify-end gap-2">
                <Button size="sm" variant="ghost" onClick={onReject}><X className="h-4 w-4" /> Reject</Button>
                <Button size="sm" onClick={onApprove} disabled={!p.account}>
                    {p.automatic ? <><Send className="h-4 w-4" /> Send</> : <><Check className="h-4 w-4" /> Mark paid</>}
                </Button>
            </div>
        );
    }

    if (p.status === 'processing' && p.provider === 'paytota') {
        return (
            <Button size="sm" variant="outline" onClick={() => router.post(`/admin/payouts/${p.id}/refresh`, {}, { preserveScroll: true })}>
                <RefreshCw className="h-4 w-4" /> Check status
            </Button>
        );
    }

    return null;
}

export default function AdminPayouts({ driver, payouts }: PageProps<{ driver: string; payouts: { data: P[]; links: Links } }>) {
    const [approving, setApproving] = useState<P | null>(null);
    const [rejecting, setRejecting] = useState<P | null>(null);

    return (
        <AdminLayout
            title={<>Pay<Accent>outs</Accent></>}
            subtitle={driver === 'paytota'
                ? 'Mobile Money payouts are sent through Paytota when you approve them. Bank payouts are paid by hand.'
                : 'Pay organizers from your provider or bank, then record the transfer reference here.'}
        >
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
                    { header: 'Pay to', cell: (p) => <Destination account={p.account} /> },
                    { header: 'Amount', cell: (p) => <span className="whitespace-nowrap font-bold text-ink">{money(p.amount_minor, p.currency)}</span> },
                    {
                        header: 'Status',
                        cell: (p) => (
                            <div className="max-w-[16rem]">
                                <Badge tone={tone[p.status] ?? 'neutral'}>{p.status}</Badge>
                                {p.notes && <p className="mt-1 line-clamp-2 text-xs text-slate-400" title={p.notes}>{p.notes}</p>}
                                {p.provider_ref && <p className="mt-1 truncate font-mono text-[11px] text-slate-500" title={p.provider_ref}>{p.provider_ref}</p>}
                            </div>
                        ),
                    },
                    { header: 'Requested', cell: (p) => <span className="whitespace-nowrap text-slate-500">{p.requested_at ? formatDate(p.requested_at) : '—'}</span> },
                    { header: 'Actions', align: 'right', cell: (p) => <Actions p={p} onApprove={() => setApproving(p)} onReject={() => setRejecting(p)} /> },
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
                        <div className="mt-3"><Destination account={p.account} /></div>
                        {p.notes && <p className="mt-2 text-xs text-slate-400">{p.notes}</p>}
                        <div className="mt-3 flex items-center justify-between gap-2 border-t border-tint/[0.06] pt-3">
                            <div>
                                <p className="text-lg font-extrabold text-ink">{money(p.amount_minor, p.currency)}</p>
                                <p className="text-xs text-slate-400">{p.requested_at ? `Requested ${formatDate(p.requested_at)}` : ''}</p>
                            </div>
                            <Actions p={p} onApprove={() => setApproving(p)} onReject={() => setRejecting(p)} />
                        </div>
                    </div>
                )}
            />
            <Pagination links={payouts.links} />

            {approving && <ApproveDialog payout={approving} onClose={() => setApproving(null)} />}
            {rejecting && <RejectDialog payout={rejecting} onClose={() => setRejecting(null)} />}
        </AdminLayout>
    );
}

function Dialog({ title, body, onClose, onSubmit, children, footer }: { title: string; body: ReactNode; onClose: () => void; onSubmit: (e: FormEvent) => void; children: ReactNode; footer: ReactNode }) {
    return (
        <div className="fixed inset-0 z-[70] grid place-items-center bg-black/60 p-4 backdrop-blur-sm" onClick={onClose}>
            <form onSubmit={onSubmit} onClick={(e) => e.stopPropagation()} className="w-full max-w-md rounded-[1.75rem] border border-tint/[0.08] bg-card p-6 shadow-premium">
                <h2 className="text-xl font-extrabold tracking-[-0.03em] text-ink">{title}</h2>
                <div className="mt-1 text-sm text-slate-500">{body}</div>
                <div className="mt-5 space-y-4">{children}</div>
                <div className="mt-6 flex justify-end gap-2">
                    <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                    {footer}
                </div>
            </form>
        </div>
    );
}

function ApproveDialog({ payout, onClose }: { payout: P; onClose: () => void }) {
    const form = useForm({ provider_ref: '', password: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/admin/payouts/${payout.id}/approve`, { preserveScroll: true, onSuccess: onClose, onFinish: () => form.reset('password') });
    }

    return (
        <Dialog
            title={payout.automatic ? 'Send payout' : 'Record manual payout'}
            onClose={onClose}
            onSubmit={submit}
            body={payout.automatic
                ? <>Paytota will send <b className="text-ink">{money(payout.amount_minor, payout.currency)}</b> to {payout.account?.account_name} on <span className="font-mono">{payout.account?.account_number}</span>. This can't be undone.</>
                : <>Send <b className="text-ink">{money(payout.amount_minor, payout.currency)}</b> to {payout.organization} from your provider or bank first, then record the transfer reference.</>}
            footer={
                <Button type="submit" disabled={form.processing}>
                    {payout.automatic ? <><Send className="h-4 w-4" /> Send {money(payout.amount_minor, payout.currency)}</> : <><Check className="h-4 w-4" /> Mark paid</>}
                </Button>
            }
        >
            {!payout.automatic && (
                <Field label="Transaction reference" error={form.errors.provider_ref}>
                    <Input value={form.data.provider_ref} onChange={(e) => form.setData('provider_ref', e.target.value)} placeholder="From Paytota or your bank" required />
                </Field>
            )}
            <Field label="Confirm with your password" error={form.errors.password}>
                <Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} autoComplete="current-password" required />
            </Field>
        </Dialog>
    );
}

function RejectDialog({ payout, onClose }: { payout: P; onClose: () => void }) {
    const form = useForm({ reason: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/admin/payouts/${payout.id}/reject`, { preserveScroll: true, onSuccess: onClose });
    }

    return (
        <Dialog
            title="Reject payout"
            onClose={onClose}
            onSubmit={submit}
            body={<>The {money(payout.amount_minor, payout.currency)} goes back to {payout.organization}'s balance and they see your reason.</>}
            footer={<Button type="submit" variant="danger" disabled={form.processing}><X className="h-4 w-4" /> Reject</Button>}
        >
            <Field label="Reason" error={form.errors.reason}>
                <Textarea rows={3} value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder="e.g. The Mobile Money number isn't registered to the organizer." required />
            </Field>
        </Dialog>
    );
}
