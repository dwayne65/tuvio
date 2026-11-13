import { Button } from '@/Components/ui/Button';
import { Accent } from '@/Components/app/Kit';
import FeePayerChoice from '@/Components/app/FeePayerChoice';
import { TicketPreview, TicketTypeDraft, TicketTypeEditor, draftPayload, newDraft } from '@/Components/app/TicketTypeEditor';
import { Field, Input, Select, Textarea } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { TICKET_TIERS, TicketTier } from '@/lib/ticketTiers';
import { ArrowLeft, ArrowRight, Check, ImageIcon, Pencil, Plus, Trash2, Upload } from 'lucide-react';
import { ChangeEvent, useState } from 'react';

interface Cat { id: number; name: string; slug: string }

const steps = ['Basics', 'Date & location', 'Tickets', 'Review'];

export default function CreateEvent({ categories, currency, commission_bps }: PageProps<{ categories: Cat[]; currency: string; commission_bps: number }>) {
    const [step, setStep] = useState(0);
    const { data, setData, post, transform, processing, errors } = useForm<any>({
        title: '', description: '', event_category_id: '', type: 'physical', visibility: 'public',
        start_at: '', end_at: '', timezone: 'Africa/Kampala',
        venue_name: '', venue_address: '', city: '', online_url: '', currency,
        refund_policy: '',
        fee_payer: 'organizer',
        cover: null as File | null,
        ticket_types: [newDraft('general')] as TicketTypeDraft[],
    });
    const [coverPreview, setCoverPreview] = useState<string | null>(null);
    const [openTicket, setOpenTicket] = useState(0);

    function setTT(i: number, next: TicketTypeDraft) {
        const all = [...data.ticket_types];
        all[i] = next;
        setData('ticket_types', all);
    }
    function addTT(tier: TicketTier) {
        setData('ticket_types', [...data.ticket_types, newDraft(tier)]);
        setOpenTicket(data.ticket_types.length);
    }
    function removeTT(i: number) {
        setData('ticket_types', data.ticket_types.filter((_: TicketTypeDraft, j: number) => j !== i));
        setOpenTicket((open) => (open === i ? -1 : open > i ? open - 1 : open));
    }

    function chooseCover(e: ChangeEvent<HTMLInputElement>) {
        const file = e.target.files?.[0];
        if (!file) return;

        setData('cover', file);
        setCoverPreview(URL.createObjectURL(file));
    }

    function submit() {
        // Inertia's transform callback is the payload source. Passing { data } to
        // post() only adds an option and does not replace the form payload.
        transform((form) => ({
            ...form,
            ticket_types: form.ticket_types.map(draftPayload),
        }));

        post('/dashboard/events', {
            forceFormData: true,
            preserveScroll: true,
            onError: (formErrors) => {
                const fields = Object.keys(formErrors);
                const ticketField = fields.find((field) => field.startsWith('ticket_types.'));
                if (ticketField) {
                    setStep(2);
                    setOpenTicket(parseInt(ticketField.split('.')[1] ?? '0', 10) || 0);
                }
                else if (fields.some((field) => ['start_at', 'end_at', 'type', 'venue_name', 'venue_address', 'city', 'online_url'].includes(field))) setStep(1);
                else setStep(0);
            },
        });
    }

    return (
        <AppLayout title={<>Create an <Accent>event</Accent></>} back={{ href: '/dashboard/events', label: 'My events' }}>
            <Head title="Create event" />
            <div className="mx-auto max-w-2xl">
                {/* Stepper */}
                <div className="mb-5">
                    <div className="flex items-center justify-between text-sm">
                        <span className="font-bold text-ink">{steps[step]}</span>
                        <span className="text-slate-400">Step {step + 1} of {steps.length}</span>
                    </div>
                    <div className="mt-2.5 grid grid-cols-4 gap-1.5">
                        {steps.map((s, i) => (
                            <button
                                key={s}
                                type="button"
                                onClick={() => i < step && setStep(i)}
                                disabled={i >= step}
                                aria-label={`${s}${i < step ? ' (completed)' : ''}`}
                                className={cn('h-1.5 rounded-full transition-colors', i <= step ? 'bg-brand-500' : 'bg-tint/[0.08]')}
                            />
                        ))}
                    </div>
                    <ol className="mt-3 hidden grid-cols-4 gap-1.5 sm:grid">
                        {steps.map((s, i) => (
                            <li key={s} className={cn('flex items-center gap-1.5 text-xs font-semibold', i <= step ? 'text-ink' : 'text-slate-400')}>
                                {i < step && <Check className="h-3.5 w-3.5 text-brand-500" />} {s}
                            </li>
                        ))}
                    </ol>
                </div>

                <section className="space-y-4 rounded-[1.5rem] border border-tint/[0.07] bg-card p-5 shadow-[var(--shadow-card)] sm:p-7">
                        {step === 0 && (
                            <>
                                <Field label="Event title" error={errors.title}>
                                    <Input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="African Business Summit 2026" />
                                </Field>
                                <Field label="Description" error={errors.description}>
                                    <Textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={5} placeholder="What should people expect?" />
                                </Field>
                                <Field label="Category" error={errors.event_category_id}>
                                    <Select value={data.event_category_id} onChange={(e) => setData('event_category_id', e.target.value)}>
                                        <option value="">Select a category</option>
                                        {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                                    </Select>
                                </Field>
                                <div>
                                    <p className="text-sm font-semibold text-slate-700">Event cover image <span className="font-normal text-slate-400">(optional)</span></p>
                                    <label className="mt-1.5 block cursor-pointer overflow-hidden rounded-2xl border border-dashed border-brand-400/60 bg-brand-500/5 transition-colors hover:bg-brand-500/10">
                                        {coverPreview ? (
                                            <img src={coverPreview} alt="Event cover preview" className="aspect-[16/7] w-full object-cover" />
                                        ) : (
                                            <span className="flex aspect-[16/7] flex-col items-center justify-center text-brand-600">
                                                <ImageIcon className="h-8 w-8" />
                                                <span className="mt-2 text-sm font-semibold">Add a cover image</span>
                                                <span className="mt-1 text-xs text-slate-500">JPG, PNG or WebP · up to 4 MB</span>
                                            </span>
                                        )}
                                        <input type="file" accept="image/*" onChange={chooseCover} className="sr-only" />
                                    </label>
                                    {data.cover && <p className="mt-2 flex items-center gap-1.5 text-xs font-medium text-emerald-600"><Upload className="h-3.5 w-3.5" /> Cover ready to save with event</p>}
                                </div>
                            </>
                        )}

                        {step === 1 && (
                            <>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field label="Starts" error={errors.start_at}>
                                        <Input type="datetime-local" value={data.start_at} onChange={(e) => setData('start_at', e.target.value)} />
                                    </Field>
                                    <Field label="Ends" error={errors.end_at}>
                                        <Input type="datetime-local" value={data.end_at} onChange={(e) => setData('end_at', e.target.value)} />
                                    </Field>
                                </div>
                                <Field label="Event type">
                                    <Select value={data.type} onChange={(e) => setData('type', e.target.value)}>
                                        <option value="physical">In person</option>
                                        <option value="online">Online</option>
                                        <option value="hybrid">Hybrid</option>
                                    </Select>
                                </Field>
                                {data.type !== 'online' && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field label="Venue name"><Input value={data.venue_name} onChange={(e) => setData('venue_name', e.target.value)} /></Field>
                                        <Field label="City"><Input value={data.city} onChange={(e) => setData('city', e.target.value)} /></Field>
                                        <div className="sm:col-span-2"><Field label="Address"><Input value={data.venue_address} onChange={(e) => setData('venue_address', e.target.value)} /></Field></div>
                                    </div>
                                )}
                                {data.type !== 'physical' && (
                                    <Field label="Online URL" error={errors.online_url}><Input value={data.online_url} onChange={(e) => setData('online_url', e.target.value)} placeholder="https://…" /></Field>
                                )}
                            </>
                        )}

                        {step === 2 && (
                            <div className="space-y-3">
                                <div>
                                    <h2 className="text-lg font-bold text-ink">Tickets</h2>
                                    <p className="text-sm text-slate-500">Offer one or more tiers. Each tier gets its own ticket design for attendees.</p>
                                </div>
                                {typeof errors.ticket_types === 'string' && <p className="text-sm text-danger">{errors.ticket_types}</p>}

                                {data.ticket_types.map((t: TicketTypeDraft, i: number) => {
                                    const hasError = Object.keys(errors).some((key) => key.startsWith(`ticket_types.${i}.`));
                                    return openTicket === i ? (
                                        <div key={i} className="rounded-[1.25rem] border border-brand-500/30 bg-tint/[0.015] p-4 sm:p-5">
                                            <div className="mb-4 flex items-center justify-between">
                                                <span className="text-sm font-bold text-ink">Ticket {i + 1}</span>
                                                <div className="flex items-center gap-1">
                                                    {data.ticket_types.length > 1 && (
                                                        <button type="button" onClick={() => removeTT(i)} aria-label={`Remove ticket ${i + 1}`} className="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-rose-500/10 hover:text-danger"><Trash2 className="h-4 w-4" /></button>
                                                    )}
                                                    <Button size="sm" variant="secondary" onClick={() => setOpenTicket(-1)}><Check className="h-4 w-4" /> Done</Button>
                                                </div>
                                            </div>
                                            <TicketTypeEditor value={t} onChange={(next) => setTT(i, next)} currency={currency} errors={errors} errorPrefix={`ticket_types.${i}.`} />
                                        </div>
                                    ) : (
                                        <div key={i} className={cn('flex items-center gap-2 rounded-[1.25rem]', hasError && 'ring-2 ring-danger/60')}>
                                            <button type="button" onClick={() => setOpenTicket(i)} className="min-w-0 flex-1 text-left" aria-label={`Edit ticket ${i + 1}`}>
                                                <TicketPreview draft={t} currency={currency} />
                                            </button>
                                            <div className="flex flex-col gap-1">
                                                <button type="button" onClick={() => setOpenTicket(i)} aria-label={`Edit ticket ${i + 1}`} className="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-tint/[0.06] hover:text-ink"><Pencil className="h-4 w-4" /></button>
                                                {data.ticket_types.length > 1 && (
                                                    <button type="button" onClick={() => removeTT(i)} aria-label={`Remove ticket ${i + 1}`} className="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-rose-500/10 hover:text-danger"><Trash2 className="h-4 w-4" /></button>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })}

                                <div className="rounded-[1.25rem] border border-dashed border-tint/15 p-4">
                                    <p className="text-sm font-semibold text-ink">Add another ticket</p>
                                    <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        {TICKET_TIERS.map((tier) => {
                                            const Icon = tier.icon;
                                            return (
                                                <button
                                                    key={tier.key}
                                                    type="button"
                                                    onClick={() => addTT(tier.key)}
                                                    className="flex items-center gap-2.5 rounded-2xl border border-tint/10 bg-card p-2.5 text-left text-sm font-semibold text-ink transition hover:-translate-y-0.5 hover:border-brand-500/50"
                                                >
                                                    <span className="grid h-8 w-8 shrink-0 place-items-center rounded-xl text-white" style={{ background: tier.swatch }}><Icon className="h-4 w-4" /></span>
                                                    <span className="flex items-center gap-1"><Plus className="h-3.5 w-3.5 text-slate-400" />{tier.label}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="pt-2">
                                    <FeePayerChoice value={data.fee_payer} onChange={(v) => setData('fee_payer', v)} commissionBps={commission_bps} currency={currency} />
                                </div>
                            </div>
                        )}

                        {step === 3 && (
                            <div className="space-y-3 text-sm">
                                <Row label="Title" value={data.title} />
                                <Row label="When" value={data.start_at} />
                                <Row label="Type" value={data.type} />
                                <Row label="Location" value={data.type === 'online' ? 'Online' : [data.venue_name, data.city].filter(Boolean).join(', ')} />
                                <Row label="Tickets" value={data.ticket_types.map((t: TicketTypeDraft) => t.name.trim() || 'Untitled').join(' · ')} />
                                <Row label="Tuvio fee" value={data.fee_payer === 'buyer' ? 'Paid by buyers at checkout' : 'Paid by you from earnings'} />
                                <p className="pt-2 text-slate-500">Your event will be created as a <strong>draft</strong>. You can review and publish it next.</p>
                            </div>
                        )}

                        <div className="flex items-center justify-between gap-3 border-t border-tint/[0.06] pt-5">
                            <Button variant="ghost" onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0}>
                                <ArrowLeft className="h-4 w-4" /> Back
                            </Button>
                            {step < steps.length - 1 ? (
                                <Button onClick={() => setStep((s) => s + 1)}>
                                    Continue <ArrowRight className="h-4 w-4" />
                                </Button>
                            ) : (
                                <Button onClick={submit} disabled={processing}>
                                    <Check className="h-4 w-4" /> Create event
                                </Button>
                            )}
                        </div>
                </section>
            </div>
        </AppLayout>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex justify-between gap-4 border-b border-tint/[0.06] pb-2.5">
            <span className="text-slate-400">{label}</span>
            <span className="truncate text-right font-semibold text-ink">{value || '—'}</span>
        </div>
    );
}
