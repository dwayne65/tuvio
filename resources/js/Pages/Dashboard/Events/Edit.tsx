import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Panel } from '@/Components/app/Kit';
import FeePayerChoice, { FeePayer } from '@/Components/app/FeePayerChoice';
import { TicketTypeDraft, TicketTypeEditor, TicketTypeRecord, draftFromRecord, draftPayload, newDraft } from '@/Components/app/TicketTypeEditor';
import { Field, Input, Select, Textarea } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { tierMeta } from '@/lib/ticketTiers';
import { cn, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ExternalLink, Gift, ImageIcon, Pencil, Plus, Smartphone, Trash2, Upload, X } from 'lucide-react';
import { ChangeEvent, FormEvent, useEffect, useState } from 'react';

type TT = TicketTypeRecord;
interface Ev {
    id: number; title: string; slug: string; description: string | null; status: string; review_note: string | null;
    type: string; visibility: string; event_category_id: number | null;
    start_at: string | null; end_at: string | null;
    venue_name: string | null; venue_address: string | null; city: string | null;
    online_url: string | null; currency: string; refund_policy: string | null;
    fee_payer: FeePayer; commission_bps: number;
    public_url: string; cover_url: string | null; ticket_types: TT[];
    sell_on_ussd: boolean;
    ussd_dial: string | null;
}

/** Upcoming events on USSD against the plan limit: null is unlimited, 0 means the plan has no USSD. */
interface UssdUsage { used: number; limit: number | null }

const tone: Record<string, 'success' | 'warning' | 'neutral' | 'danger' | 'brand'> = {
    published: 'success', draft: 'neutral', unpublished: 'warning', cancelled: 'danger', in_review: 'brand', rejected: 'danger',
};

export default function EditEvent({ event, categories, ussd }: PageProps<{ event: Ev; categories: { id: number; name: string }[]; ussd: UssdUsage }>) {
    const form = useForm<any>({
        title: event.title, description: event.description ?? '', event_category_id: event.event_category_id ?? '',
        type: event.type, visibility: event.visibility, start_at: event.start_at ?? '', end_at: event.end_at ?? '',
        venue_name: event.venue_name ?? '', venue_address: event.venue_address ?? '', city: event.city ?? '',
        online_url: event.online_url ?? '', currency: event.currency, refund_policy: event.refund_policy ?? '',
        fee_payer: event.fee_payer,
        timezone: 'Africa/Kampala',
    });
    const [cover, setCover] = useState<File | null>(null);
    const [coverPreview, setCoverPreview] = useState<string | null>(event.cover_url);
    const [editing, setEditing] = useState<TT | 'new' | null>(null);

    function save(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, ticket_types: [] }));
        form.patch(`/dashboard/events/${event.slug}`, { preserveScroll: true });
    }

    function chooseCover(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;
        setCover(file);
        setCoverPreview(URL.createObjectURL(file));
    }

    function uploadCover(e: FormEvent) {
        e.preventDefault();
        if (!cover) return;
        router.post(`/dashboard/events/${event.slug}/cover`, { cover }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => setCover(null),
        });
    }

    return (
        <AppLayout
            back={{ href: '/dashboard/events', label: 'My events' }}
            title={event.title}
            subtitle={
                <span className="flex flex-wrap items-center gap-2">
                    <Badge tone={tone[event.status] ?? 'neutral'}>{event.status.replace('_', ' ')}</Badge>
                    {event.status === 'published' && (
                        <a href={event.public_url} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:underline">
                            View public page <ExternalLink className="h-3.5 w-3.5" />
                        </a>
                    )}
                </span>
            }
            actions={
                event.status === 'in_review' ? (
                    <Button variant="outline" onClick={() => router.post(`/dashboard/events/${event.slug}/unpublish`, {}, { preserveScroll: true })}>Withdraw from review</Button>
                ) : event.status !== 'published' ? (
                    <Button onClick={() => router.post(`/dashboard/events/${event.slug}/publish`, {}, { preserveScroll: true })}>
                        {event.status === 'rejected' ? 'Resubmit for review' : 'Submit for review'}
                    </Button>
                ) : (
                    <Button variant="outline" onClick={() => router.post(`/dashboard/events/${event.slug}/unpublish`, {}, { preserveScroll: true })}>Unpublish</Button>
                )
            }
        >
            <Head title={`Manage · ${event.title}`} />

            {event.status === 'in_review' && (
                <div className="mb-5 rounded-[1.25rem] border border-brand-400/30 bg-brand-500/10 px-5 py-4 text-sm text-ink">
                    <strong>Waiting for approval.</strong> A TUVIO admin is reviewing this event. It goes live as soon as it is approved, and we will email you.
                </div>
            )}
            {event.status === 'rejected' && (
                <div className="mb-5 rounded-[1.25rem] border border-rose-400/30 bg-rose-500/10 px-5 py-4 text-sm text-ink">
                    <strong>Changes requested.</strong> {event.review_note ?? 'An admin asked for changes before this event can go live.'} Update the event, then resubmit it for review.
                </div>
            )}

            <div className="grid gap-5 lg:grid-cols-3">
                {/* Details */}
                <Panel title="Event details" subtitle="Changes go live as soon as you save." className="h-fit lg:col-span-2" bodyClassName="pt-4">
                        <form onSubmit={save} className="space-y-4">
                            <Field label="Title" error={form.errors.title}><Input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} /></Field>
                            <Field label="Description"><Textarea value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} rows={5} /></Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Category">
                                    <Select value={form.data.event_category_id} onChange={(e) => form.setData('event_category_id', e.target.value)}>
                                        <option value="">None</option>
                                        {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </Select>
                                </Field>
                                <Field label="Visibility">
                                    <Select value={form.data.visibility} onChange={(e) => form.setData('visibility', e.target.value)}>
                                        <option value="public">Public</option>
                                        <option value="unlisted">Unlisted</option>
                                        <option value="private">Private</option>
                                    </Select>
                                </Field>
                                <Field label="Starts" error={form.errors.start_at}><Input type="datetime-local" value={form.data.start_at} onChange={(e) => form.setData('start_at', e.target.value)} /></Field>
                                <Field label="Ends"><Input type="datetime-local" value={form.data.end_at} onChange={(e) => form.setData('end_at', e.target.value)} /></Field>
                            </div>
                            <FeePayerChoice value={form.data.fee_payer} onChange={(v) => form.setData('fee_payer', v)} commissionBps={event.commission_bps} currency={event.currency} />
                            <Button type="submit" disabled={form.processing}>Save changes</Button>
                        </form>
                </Panel>

                <div className="space-y-5">
                    {/* Cover image */}
                    <Panel title="Event cover" subtitle="A strong image makes your event memorable." bodyClassName="pt-4">
                            <div className="overflow-hidden rounded-2xl bg-tint/[0.04]">
                                {coverPreview ? <img src={coverPreview} alt="Event cover preview" className="aspect-[16/10] w-full object-cover" /> : <div className="flex aspect-[16/10] flex-col items-center justify-center text-slate-400"><ImageIcon className="h-10 w-10" /><p className="mt-2 text-sm">No cover image yet</p></div>}
                            </div>
                            <form onSubmit={uploadCover} className="mt-4">
                                <label className="flex cursor-pointer items-center justify-center gap-2 rounded-full border border-dashed border-brand-400/60 bg-brand-500/5 px-4 py-3 text-sm font-semibold text-brand-600 transition-colors hover:bg-brand-500/10">
                                    <Upload className="h-4 w-4" /> {cover ? 'Choose a different image' : 'Upload cover image'}
                                    <input type="file" accept="image/*" onChange={chooseCover} className="sr-only" />
                                </label>
                                {cover && <Button type="submit" className="mt-2 w-full" size="sm">Save cover</Button>}
                            </form>
                    </Panel>

                    <UssdPanel slug={event.slug} enabled={event.sell_on_ussd} dial={event.ussd_dial} usage={ussd} />

                    {/* Ticket types */}
                    <Panel
                        title="Ticket types"
                        subtitle={event.ticket_types.length ? `${event.ticket_types.length} ${event.ticket_types.length === 1 ? 'tier' : 'tiers'} on offer` : 'Add at least one before publishing.'}
                        bodyClassName="pt-4"
                    >
                        <div className="space-y-2.5">
                            {event.ticket_types.map((t) => (
                                <TicketTypeRow
                                    key={t.id}
                                    ticket={t}
                                    currency={event.currency}
                                    onEdit={() => setEditing(t)}
                                    onDelete={() => router.delete(`/dashboard/events/${event.slug}/ticket-types/${t.id}`, { preserveScroll: true })}
                                />
                            ))}
                        </div>
                        <Button variant="outline" size="sm" className="mt-4 w-full" onClick={() => setEditing('new')}>
                            <Plus className="h-4 w-4" /> Add ticket type
                        </Button>
                    </Panel>
                </div>
            </div>

            <AnimatePresence>
                {editing && (
                    <TicketTypeModal
                        key={editing === 'new' ? 'new' : editing.id}
                        slug={event.slug}
                        currency={event.currency}
                        record={editing === 'new' ? null : editing}
                        onClose={() => setEditing(null)}
                    />
                )}
            </AnimatePresence>
        </AppLayout>
    );
}

function UssdPanel({ slug, enabled, dial, usage }: { slug: string; enabled: boolean; dial: string | null; usage: UssdUsage }) {
    const [saving, setSaving] = useState(false);
    const full = !enabled && usage.limit !== null && usage.used >= usage.limit;

    function toggle() {
        router.post(`/dashboard/events/${slug}/ussd`, { sell_on_ussd: !enabled }, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
        });
    }

    return (
        <Panel title="Sell on USSD" subtitle="Buyers without data can dial the TUVIO USSD code and pay with Mobile Money." bodyClassName="pt-4">
            {usage.limit === 0 ? (
                <p className="text-sm text-slate-500">
                    Your plan doesn't include USSD ticket sales.{' '}
                    <Link href="/dashboard/subscription" className="font-semibold text-brand-600 hover:underline">Upgrade to sell on USSD</Link>
                </p>
            ) : (
                <>
                    <button
                        type="button"
                        role="switch"
                        aria-checked={enabled}
                        disabled={saving || full}
                        onClick={toggle}
                        className="flex w-full items-center justify-between gap-3 rounded-2xl bg-tint/[0.04] px-4 py-3 text-left text-sm font-semibold text-ink disabled:opacity-60"
                    >
                        <span className="inline-flex items-center gap-2">
                            <Smartphone className="h-4 w-4 text-brand-500" /> {enabled ? 'On sale over USSD' : 'Not on USSD'}
                        </span>
                        <span className={cn('relative h-5 w-9 shrink-0 rounded-full transition-colors', enabled ? 'bg-brand-500' : 'bg-tint/15')}>
                            <span className={cn('absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all', enabled ? 'left-[1.125rem]' : 'left-0.5')} />
                        </span>
                    </button>
                    {enabled && dial && (
                        <div className="mt-3 rounded-2xl border border-brand-500/20 bg-brand-500/[0.06] px-4 py-3">
                            <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-500">Your event's USSD code</p>
                            <p className="mt-1 font-mono text-2xl font-extrabold text-ink">{dial}</p>
                            <p className="mt-1 text-xs text-slate-500">Put it on posters and radio ads. Callers who dial it go straight to this event's tickets.</p>
                        </div>
                    )}
                    <p className="mt-3 text-xs text-slate-500">
                        {usage.limit === null
                            ? 'Your plan includes USSD sales for unlimited events.'
                            : `${usage.used} of ${usage.limit} USSD events in use. An event frees its slot once it starts.`}
                        {full && (
                            <>
                                {' '}Switch another event off, or{' '}
                                <Link href="/dashboard/subscription" className="font-semibold text-brand-600 hover:underline">upgrade for unlimited</Link>.
                            </>
                        )}
                    </p>
                </>
            )}
        </Panel>
    );
}

function TicketTypeRow({ ticket: t, currency, onEdit, onDelete }: { ticket: TT; currency: string; onEdit: () => void; onDelete: () => void }) {
    const tier = tierMeta(t.tier);
    const Icon = tier.icon;
    const soldShare = t.quantity_total ? Math.min(100, (t.quantity_sold / t.quantity_total) * 100) : null;

    return (
        <div className="group flex items-stretch overflow-hidden rounded-2xl border border-tint/[0.07] bg-tint/[0.02]">
            <span className="grid w-12 shrink-0 place-items-center text-white" style={{ background: tier.swatch }}>
                <Icon className="h-[18px] w-[18px] drop-shadow" />
            </span>
            <div className="min-w-0 flex-1 p-3">
                <div className="flex items-center gap-2">
                    <p className="truncate font-semibold text-ink">{t.name}</p>
                    {t.status !== 'active' && <Badge tone="warning">{t.status}</Badge>}
                </div>
                <p className="text-xs text-slate-500">
                    <span className="font-semibold text-ink">{t.kind === 'free' ? 'Free' : money(t.price_minor, currency)}</span> ·{' '}
                    {t.quantity_total === null ? `${t.quantity_sold} sold · Unlimited` : `${t.quantity_sold}/${t.quantity_total} sold`}
                    {t.perks.length > 0 && (
                        <span className="ml-1.5 inline-flex items-center gap-0.5"><Gift className="h-3 w-3" /> {t.perks.length}</span>
                    )}
                </p>
                {soldShare !== null && (
                    <div className="mt-2 h-1 overflow-hidden rounded-full bg-tint/[0.06]">
                        <div className="h-full rounded-full" style={{ width: `${soldShare}%`, background: tier.swatch }} />
                    </div>
                )}
            </div>
            <div className="flex items-center pr-1.5">
                <button onClick={onEdit} aria-label={`Edit ${t.name}`} className="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-tint/[0.06] hover:text-ink">
                    <Pencil className="h-4 w-4" />
                </button>
                {t.quantity_sold === 0 && (
                    <button onClick={onDelete} aria-label={`Delete ${t.name}`} className="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-rose-500/10 hover:text-danger">
                        <Trash2 className="h-4 w-4" />
                    </button>
                )}
            </div>
        </div>
    );
}

/** Add or edit a ticket type: bottom sheet on phones, dialog on desktop. */
function TicketTypeModal({ slug, currency, record, onClose }: { slug: string; currency: string; record: TT | null; onClose: () => void }) {
    const form = useForm<TicketTypeDraft>(record ? draftFromRecord(record) : newDraft('general'));

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => draftPayload(d) as unknown as TicketTypeDraft);
        const options = { preserveScroll: true, onSuccess: onClose };
        if (record) {
            form.patch(`/dashboard/events/${slug}/ticket-types/${record.id}`, options);
        } else {
            form.post(`/dashboard/events/${slug}/ticket-types`, options);
        }
    }

    return (
        <motion.div
            className="fixed inset-0 z-[60] flex items-end justify-center bg-black/60 backdrop-blur-sm sm:items-center sm:p-4"
            onClick={onClose}
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
        >
            <motion.form
                onSubmit={submit}
                role="dialog"
                aria-modal="true"
                aria-label={record ? `Edit ${record.name}` : 'Add ticket type'}
                onClick={(e) => e.stopPropagation()}
                className="flex max-h-[94dvh] w-full max-w-2xl flex-col overflow-hidden rounded-t-[1.75rem] border border-tint/10 bg-card shadow-premium sm:max-h-[calc(100dvh-2rem)] sm:rounded-[1.75rem]"
                initial={{ y: 40, opacity: 0.6 }}
                animate={{ y: 0, opacity: 1 }}
                exit={{ y: 40, opacity: 0 }}
                transition={{ type: 'spring', stiffness: 380, damping: 34 }}
            >
                <header className="flex items-center justify-between gap-3 border-b border-tint/[0.06] px-5 py-4 sm:px-6">
                    <div>
                        <h2 className="text-lg font-bold text-ink">{record ? 'Edit ticket type' : 'New ticket type'}</h2>
                        <p className="text-sm text-slate-500">{record && record.quantity_sold > 0 ? `${record.quantity_sold} already sold — changes apply to future sales.` : 'Choose a tier, set the price and add perks.'}</p>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Close" className="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-tint/[0.05] text-slate-500 hover:text-ink">
                        <X className="h-4 w-4" />
                    </button>
                </header>

                <div className="flex-1 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                    <TicketTypeEditor value={form.data} onChange={(next) => form.setData(next)} currency={currency} errors={form.errors as Record<string, string>} />
                </div>

                <footer className="flex items-center justify-end gap-2 border-t border-tint/[0.06] px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-6">
                    <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={form.processing} className={cn(form.processing && 'opacity-70')}>
                        {record ? 'Save changes' : 'Add ticket type'}
                    </Button>
                </footer>
            </motion.form>
        </motion.div>
    );
}
