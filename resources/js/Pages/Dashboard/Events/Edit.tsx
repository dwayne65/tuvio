import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Panel } from '@/Components/app/Kit';
import { TicketTypeDraft, TicketTypeEditor, TicketTypeRecord, draftFromRecord, draftPayload, newDraft } from '@/Components/app/TicketTypeEditor';
import { Field, Input, Select, Textarea } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { tierMeta } from '@/lib/ticketTiers';
import { cn, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { ExternalLink, Gift, ImageIcon, Pencil, Plus, Trash2, Upload, X } from 'lucide-react';
import { ChangeEvent, FormEvent, useEffect, useState } from 'react';

type TT = TicketTypeRecord;
interface Ev {
    id: number; title: string; slug: string; description: string | null; status: string;
    type: string; visibility: string; event_category_id: number | null;
    start_at: string | null; end_at: string | null;
    venue_name: string | null; venue_address: string | null; city: string | null;
    online_url: string | null; currency: string; refund_policy: string | null;
    public_url: string; cover_url: string | null; ticket_types: TT[];
}

const tone: Record<string, 'success' | 'warning' | 'neutral' | 'danger'> = {
    published: 'success', draft: 'neutral', unpublished: 'warning', cancelled: 'danger',
};

export default function EditEvent({ event, categories }: PageProps<{ event: Ev; categories: { id: number; name: string }[] }>) {
    const form = useForm<any>({
        title: event.title, description: event.description ?? '', event_category_id: event.event_category_id ?? '',
        type: event.type, visibility: event.visibility, start_at: event.start_at ?? '', end_at: event.end_at ?? '',
        venue_name: event.venue_name ?? '', venue_address: event.venue_address ?? '', city: event.city ?? '',
        online_url: event.online_url ?? '', currency: event.currency, refund_policy: event.refund_policy ?? '',
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
                    <Badge tone={tone[event.status] ?? 'neutral'}>{event.status}</Badge>
                    {event.status === 'published' && (
                        <a href={event.public_url} target="_blank" rel="noopener" className="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:underline">
                            View public page <ExternalLink className="h-3.5 w-3.5" />
                        </a>
                    )}
                </span>
            }
            actions={
                event.status !== 'published' ? (
                    <Button onClick={() => router.post(`/dashboard/events/${event.slug}/publish`, {}, { preserveScroll: true })}>Publish event</Button>
                ) : (
                    <Button variant="outline" onClick={() => router.post(`/dashboard/events/${event.slug}/unpublish`, {}, { preserveScroll: true })}>Unpublish</Button>
                )
            }
        >
            <Head title={`Manage · ${event.title}`} />

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
