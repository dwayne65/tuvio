import { Accent, Avatar, EmptyState, FilterChips, Pagination } from '@/Components/app/Kit';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Textarea } from '@/Components/ui/Input';
import AdminLayout from '@/Layouts/AdminLayout';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDateTime } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronDown, Inbox, Lock, LockOpen, Reply, Send } from 'lucide-react';
import { FormEvent, ReactNode, useState } from 'react';

type T = {
    reference: string; name: string; email: string; subject: string; message: string; event: string | null;
    status: 'open' | 'answered' | 'closed'; reply: string | null; replied_at: string | null; at: string;
};
type Links = { url: string | null; label: string; active: boolean }[];
type Status = 'open' | 'answered' | 'closed' | 'all';

const tone = { open: 'warning', answered: 'success', closed: 'neutral' } as const;

function TicketCard({ t, base }: { t: T; base: string }) {
    const [open, setOpen] = useState(t.status === 'open');
    const form = useForm({ reply: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`${base}/${t.reference}/reply`, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <article className="overflow-hidden rounded-[1.25rem] border border-tint/[0.07] bg-card shadow-[var(--shadow-card)]">
            <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className="flex w-full items-start gap-3 p-4 text-left sm:p-5">
                <Avatar name={t.name} />
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <p className="truncate font-bold text-ink">{t.subject}</p>
                        <Badge tone={tone[t.status]} className="shrink-0">{t.status}</Badge>
                    </div>
                    <p className="truncate text-xs text-slate-400">
                        {t.name} · {t.email} · {formatDateTime(t.at)}
                    </p>
                    {t.event && <p className="mt-0.5 truncate text-xs font-semibold text-brand-300">{t.event}</p>}
                </div>
                <ChevronDown className={cn('mt-1 h-5 w-5 shrink-0 text-slate-400 transition-transform', open && 'rotate-180')} />
            </button>

            <AnimatePresence initial={false}>
                {open && (
                    <motion.div initial={{ height: 0 }} animate={{ height: 'auto' }} exit={{ height: 0 }} className="overflow-hidden">
                        <div className="space-y-3 border-t border-tint/[0.06] p-4 sm:p-5">
                            <p className="font-mono text-[11px] text-slate-400">{t.reference}</p>
                            <p className="whitespace-pre-line rounded-2xl bg-tint/[0.04] p-3.5 text-sm text-slate-600">{t.message}</p>

                            {t.reply && (
                                <div className="rounded-2xl border border-brand-500/20 bg-brand-500/[0.06] p-3.5">
                                    <p className="mb-1 flex items-center gap-1.5 text-xs font-bold text-brand-300">
                                        <Reply className="h-3.5 w-3.5" /> Your reply {t.replied_at && `· ${formatDateTime(t.replied_at)}`}
                                    </p>
                                    <p className="whitespace-pre-line text-sm text-ink">{t.reply}</p>
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-2.5">
                                <Textarea
                                    value={form.data.reply}
                                    onChange={(e) => form.setData('reply', e.target.value)}
                                    rows={3}
                                    placeholder={`Reply to ${t.name.split(' ')[0]}… (sent by email)`}
                                    aria-label="Reply"
                                    required
                                />
                                {form.errors.reply && <p className="text-sm text-danger">{form.errors.reply}</p>}
                                <div className="flex flex-wrap justify-end gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => router.post(`${base}/${t.reference}/close`, {}, { preserveScroll: true })}
                                    >
                                        {t.status === 'closed' ? <LockOpen className="h-4 w-4" /> : <Lock className="h-4 w-4" />}
                                        {t.status === 'closed' ? 'Reopen' : 'Close'}
                                    </Button>
                                    <Button type="submit" size="sm" disabled={form.processing || !form.data.reply.trim()}>
                                        <Send className="h-4 w-4" /> Send reply
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </motion.div>
                )}
            </AnimatePresence>
        </article>
    );
}

export default function SupportInbox({ tickets, counts, filters, admin }: PageProps<{
    tickets: { data: T[]; links: Links };
    counts: { open: number; answered: number; closed: number };
    filters: { status: Status };
    admin: boolean;
}>) {
    const base = admin ? '/admin/support' : '/dashboard/support';
    const title = <>Sup<Accent>port</Accent></>;
    const subtitle = admin ? 'Every customer request on the platform.' : 'Questions from buyers about your events.';

    const body = (
        <>
            <Head title="Support" />
            <FilterChips<Status>
                className="mb-4"
                options={[
                    { value: 'open', label: `Open · ${counts.open}` },
                    { value: 'answered', label: `Answered · ${counts.answered}` },
                    { value: 'closed', label: `Closed · ${counts.closed}` },
                    { value: 'all', label: 'All' },
                ]}
                value={filters.status}
                onChange={(status) => router.get(base, { status }, { preserveState: true, preserveScroll: true })}
            />
            <div className="space-y-3">
                {tickets.data.length === 0 ? (
                    <EmptyState icon={Inbox} title="Inbox zero" body="No requests here. Buyers reach you through the Contact page with their order reference." />
                ) : (
                    tickets.data.map((t) => <TicketCard key={t.reference} t={t} base={base} />)
                )}
            </div>
            <Pagination links={tickets.links} />
        </>
    );

    return admin ? <AdminLayout title={title} subtitle={subtitle}>{body}</AdminLayout> : <AppLayout title={title} subtitle={subtitle}>{body as ReactNode}</AppLayout>;
}
