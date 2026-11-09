import { Field, Input, Textarea } from '@/Components/ui/Input';
import { cn, money } from '@/lib/utils';
import { TICKET_TIERS, TicketTier, tierMeta } from '@/lib/ticketTiers';
import { Banknote, CalendarClock, Check, ChevronDown, FileText, Gift, Infinity as InfinityIcon, Layers, Minus, Plus, Tag, Users, X } from 'lucide-react';
import { KeyboardEvent, ReactNode, useState } from 'react';

export interface TicketTypeDraft {
    tier: TicketTier;
    name: string;
    kind: 'paid' | 'free';
    price_major: string;
    quantity_total: string;
    max_per_order: string;
    description: string;
    perks: string[];
    sales_start_at: string;
    sales_end_at: string;
    status: 'active' | 'paused' | 'hidden';
}

/** Shape the dashboard sends for an existing ticket type. */
export interface TicketTypeRecord {
    id: number;
    name: string;
    tier: string;
    kind: string;
    description: string | null;
    perks: string[];
    price_minor: number;
    quantity_total: number | null;
    quantity_sold: number;
    max_per_order: number;
    sales_start_at: string | null;
    sales_end_at: string | null;
    status: string;
}

export function newDraft(tier: TicketTier = 'general'): TicketTypeDraft {
    return {
        tier,
        name: tierMeta(tier).presetName,
        kind: 'paid',
        price_major: '',
        quantity_total: '',
        max_per_order: '10',
        description: '',
        perks: [],
        sales_start_at: '',
        sales_end_at: '',
        status: 'active',
    };
}

export function draftFromRecord(t: TicketTypeRecord): TicketTypeDraft {
    return {
        tier: tierMeta(t.tier).key,
        name: t.name,
        kind: t.kind === 'free' ? 'free' : 'paid',
        price_major: t.kind === 'free' ? '' : String(t.price_minor / 100),
        quantity_total: t.quantity_total === null ? '' : String(t.quantity_total),
        max_per_order: String(t.max_per_order),
        description: t.description ?? '',
        perks: t.perks,
        sales_start_at: t.sales_start_at ?? '',
        sales_end_at: t.sales_end_at ?? '',
        status: t.status === 'paused' || t.status === 'hidden' ? t.status : 'active',
    };
}

/** Converts a draft into the payload the ticket type endpoints validate. */
export function draftPayload(d: TicketTypeDraft) {
    return {
        tier: d.tier,
        name: d.name.trim(),
        kind: d.kind,
        price_minor: d.kind === 'free' ? 0 : Math.round(parseFloat(d.price_major || '0') * 100),
        quantity_total: d.quantity_total ? parseInt(d.quantity_total, 10) : null,
        max_per_order: parseInt(d.max_per_order || '10', 10),
        description: d.description.trim() || null,
        perks: d.perks,
        sales_start_at: d.sales_start_at || null,
        sales_end_at: d.sales_end_at || null,
        status: d.status,
    };
}

const MAX_PERKS = 8;

interface Props {
    value: TicketTypeDraft;
    onChange: (next: TicketTypeDraft) => void;
    currency: string;
    errors?: Record<string, string | undefined>;
    /** Prefix for server error keys, e.g. "ticket_types.0." on the create-event form. */
    errorPrefix?: string;
}

export function TicketTypeEditor({ value: d, onChange, currency, errors = {}, errorPrefix = '' }: Props) {
    const [perkInput, setPerkInput] = useState('');
    const [showSchedule, setShowSchedule] = useState(Boolean(d.sales_start_at || d.sales_end_at));
    const tier = tierMeta(d.tier);
    const error = (field: string) => errors[`${errorPrefix}${field}`];
    const set = (patch: Partial<TicketTypeDraft>) => onChange({ ...d, ...patch });

    function chooseTier(next: TicketTier) {
        // Swap the name only while it is still the previous tier's suggestion.
        const keepName = d.name.trim() !== '' && d.name !== tier.presetName;
        set({ tier: next, name: keepName ? d.name : tierMeta(next).presetName });
    }

    function addPerk(raw: string) {
        const perk = raw.trim();
        if (!perk || d.perks.length >= MAX_PERKS || d.perks.some((p) => p.toLowerCase() === perk.toLowerCase())) {
            return;
        }
        set({ perks: [...d.perks, perk] });
        setPerkInput('');
    }

    function onPerkKey(e: KeyboardEvent<HTMLInputElement>) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addPerk(perkInput);
        } else if (e.key === 'Backspace' && perkInput === '' && d.perks.length > 0) {
            set({ perks: d.perks.slice(0, -1) });
        }
    }

    const suggestions = tier.suggestedPerks.filter((p) => !d.perks.some((x) => x.toLowerCase() === p.toLowerCase()));

    return (
        <div className="@container space-y-6">
            {/* Tier */}
            <Section icon={Layers} title="Ticket tier" hint="Each tier gets its own ticket design">
                <div role="radiogroup" aria-label="Ticket tier" className="grid grid-cols-2 gap-2.5 @min-[34rem]:grid-cols-4">
                    {TICKET_TIERS.map((t) => {
                        const active = t.key === d.tier;
                        const Icon = t.icon;
                        return (
                            <button
                                key={t.key}
                                type="button"
                                role="radio"
                                aria-checked={active}
                                onClick={() => chooseTier(t.key)}
                                className={cn(
                                    'group relative overflow-hidden rounded-2xl border bg-card text-left transition-all',
                                    active ? 'border-transparent ring-2 ring-brand-500 shadow-[0_12px_28px_-16px_rgb(47_125_255_/_0.6)]' : 'border-tint/10 hover:-translate-y-0.5 hover:border-tint/20',
                                )}
                            >
                                <span className="relative flex h-14 items-center px-3.5" style={{ background: t.swatch }}>
                                    <Icon className="h-5 w-5 text-white drop-shadow" strokeWidth={2.4} />
                                    <span className="absolute -bottom-2 left-1/2 h-4 w-4 -translate-x-1/2 rounded-full bg-card" />
                                    {active && (
                                        <span className="absolute right-2.5 top-2.5 grid h-5 w-5 place-items-center rounded-full bg-white text-brand-600">
                                            <Check className="h-3 w-3" strokeWidth={3} />
                                        </span>
                                    )}
                                </span>
                                <span className="block px-3.5 pb-3 pt-2.5">
                                    <span className="block text-sm font-bold text-ink">{t.label}</span>
                                    <span className="mt-0.5 block text-xs leading-snug text-slate-500">{t.hint}</span>
                                </span>
                            </button>
                        );
                    })}
                </div>
                {error('tier') && <p className="mt-2 text-sm text-danger">{error('tier')}</p>}
            </Section>

            <TicketPreview draft={d} currency={currency} />

            {/* Basics */}
            <Section icon={FileText} title="Details">
                <div className="grid gap-4">
                    <Field label="Ticket name" error={error('name')}>
                        <Input
                            value={d.name}
                            onChange={(e) => set({ name: e.target.value })}
                            placeholder={d.tier === 'other' ? 'e.g. Early Bird, Student, Group of 4' : tier.presetName}
                            maxLength={120}
                        />
                    </Field>
                    <Field label="Short description (optional)" error={error('description')}>
                        <Textarea
                            value={d.description}
                            onChange={(e) => set({ description: e.target.value })}
                            rows={2}
                            maxLength={500}
                            className="min-h-20"
                            placeholder="What makes this ticket special?"
                        />
                    </Field>
                </div>
            </Section>

            {/* Pricing */}
            <Section icon={Banknote} title="Pricing">
                <div className="grid gap-4 @min-[30rem]:grid-cols-2">
                    <div>
                        <span className="text-sm font-medium text-ink">Ticket type</span>
                        <Segmented
                            className="mt-1.5"
                            value={d.kind}
                            onChange={(kind) => set({ kind })}
                            options={[
                                { value: 'paid', label: 'Paid' },
                                { value: 'free', label: 'Free' },
                            ]}
                        />
                    </div>
                    {d.kind === 'paid' ? (
                        <Field label="Price" error={error('price_minor')}>
                            <div className="relative">
                                <span className="pointer-events-none absolute inset-y-0 left-1.5 my-1.5 grid place-items-center rounded-xl bg-tint/[0.06] px-2.5 text-xs font-bold text-slate-500">
                                    {currency}
                                </span>
                                <Input
                                    type="number"
                                    inputMode="decimal"
                                    min="0"
                                    step="any"
                                    value={d.price_major}
                                    onChange={(e) => set({ price_major: e.target.value })}
                                    placeholder="0"
                                    className="pl-[4.25rem] font-semibold [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                                />
                            </div>
                        </Field>
                    ) : (
                        <div className="flex items-end">
                            <p className="flex h-12 w-full items-center rounded-2xl bg-emerald-500/10 px-4 text-sm font-semibold text-emerald-700">Attendees pay nothing for this ticket</p>
                        </div>
                    )}
                </div>
            </Section>

            {/* Capacity */}
            <Section icon={Users} title="Availability">
                <div className="grid gap-4 @min-[30rem]:grid-cols-2">
                    <div>
                        <div className="flex items-center justify-between">
                            <span className="text-sm font-medium text-ink">Tickets available</span>
                            <Toggle
                                label="Unlimited"
                                checked={d.quantity_total === ''}
                                onChange={(unlimited) => set({ quantity_total: unlimited ? '' : '100' })}
                            />
                        </div>
                        {d.quantity_total === '' ? (
                            <p className="mt-1.5 flex h-12 items-center gap-2 rounded-2xl border border-dashed border-tint/15 px-4 text-sm font-medium text-slate-500">
                                <InfinityIcon className="h-4 w-4" /> No limit on sales
                            </p>
                        ) : (
                            <Input
                                className="mt-1.5"
                                type="number"
                                min="1"
                                value={d.quantity_total}
                                onChange={(e) => set({ quantity_total: e.target.value })}
                                aria-label="Tickets available"
                            />
                        )}
                        {error('quantity_total') && <p className="mt-1.5 text-sm text-danger">{error('quantity_total')}</p>}
                    </div>
                    <div>
                        <span className="text-sm font-medium text-ink">Max per order</span>
                        <Stepper
                            className="mt-1.5"
                            value={parseInt(d.max_per_order || '1', 10)}
                            min={1}
                            max={100}
                            onChange={(n) => set({ max_per_order: String(n) })}
                        />
                        {error('max_per_order') && <p className="mt-1.5 text-sm text-danger">{error('max_per_order')}</p>}
                    </div>
                </div>
                <div className="mt-4">
                    <span className="text-sm font-medium text-ink">Sales status</span>
                    <Segmented
                        className="mt-1.5"
                        value={d.status}
                        onChange={(status) => set({ status })}
                        options={[
                            { value: 'active', label: 'On sale' },
                            { value: 'paused', label: 'Paused' },
                            { value: 'hidden', label: 'Hidden' },
                        ]}
                    />
                </div>
            </Section>

            {/* Perks */}
            <Section icon={Gift} title="Perks & benefits" hint={`Shown to buyers and on the ticket · ${d.perks.length}/${MAX_PERKS}`}>
                <div className="flex min-h-12 flex-wrap items-center gap-2 rounded-2xl border border-tint/10 bg-tint/[0.04] p-2 focus-within:border-brand-500 focus-within:ring-4 focus-within:ring-brand-500/15">
                    {d.perks.map((perk) => (
                        <span key={perk} className={cn('inline-flex items-center gap-1 rounded-full py-1 pl-3 pr-1 text-xs font-semibold', tier.chip)}>
                            {perk}
                            <button
                                type="button"
                                onClick={() => set({ perks: d.perks.filter((p) => p !== perk) })}
                                aria-label={`Remove ${perk}`}
                                className="grid h-5 w-5 place-items-center rounded-full hover:bg-black/10"
                            >
                                <X className="h-3 w-3" />
                            </button>
                        </span>
                    ))}
                    {d.perks.length < MAX_PERKS && (
                        <input
                            value={perkInput}
                            onChange={(e) => setPerkInput(e.target.value)}
                            onKeyDown={onPerkKey}
                            onBlur={() => addPerk(perkInput)}
                            maxLength={60}
                            placeholder={d.perks.length ? 'Add another…' : 'Type a perk and press Enter'}
                            aria-label="Add a perk"
                            className="h-8 min-w-[10rem] flex-1 bg-transparent px-2 text-sm text-ink placeholder:text-slate-400 focus:outline-none"
                        />
                    )}
                </div>
                {suggestions.length > 0 && d.perks.length < MAX_PERKS && (
                    <div className="mt-2.5 flex flex-wrap items-center gap-1.5">
                        <span className="mr-1 text-xs font-medium text-slate-400">Suggestions:</span>
                        {suggestions.map((s) => (
                            <button
                                key={s}
                                type="button"
                                onClick={() => addPerk(s)}
                                className="inline-flex items-center gap-1 rounded-full border border-dashed border-tint/15 px-2.5 py-1 text-xs font-medium text-slate-500 transition-colors hover:border-brand-500 hover:text-brand-600"
                            >
                                <Plus className="h-3 w-3" /> {s}
                            </button>
                        ))}
                    </div>
                )}
                {error('perks') && <p className="mt-1.5 text-sm text-danger">{error('perks')}</p>}
            </Section>

            {/* Sales window */}
            <div className="rounded-2xl border border-tint/[0.08]">
                <button
                    type="button"
                    onClick={() => setShowSchedule((s) => !s)}
                    aria-expanded={showSchedule}
                    className="flex w-full items-center gap-3 px-4 py-3.5 text-left"
                >
                    <span className="grid h-9 w-9 place-items-center rounded-xl bg-brand-500/10 text-brand-300">
                        <CalendarClock className="h-4 w-4" />
                    </span>
                    <span className="min-w-0 flex-1">
                        <span className="block text-sm font-bold text-ink">Sales window</span>
                        <span className="block text-xs text-slate-500">
                            {d.sales_start_at || d.sales_end_at ? 'Scheduled' : 'Optional · sell from now until the event'}
                        </span>
                    </span>
                    <ChevronDown className={cn('h-4 w-4 text-slate-400 transition-transform', showSchedule && 'rotate-180')} />
                </button>
                {showSchedule && (
                    <div className="grid gap-4 border-t border-tint/[0.06] p-4 @min-[30rem]:grid-cols-2">
                        <Field label="Sales start" error={error('sales_start_at')}>
                            <Input type="datetime-local" value={d.sales_start_at} onChange={(e) => set({ sales_start_at: e.target.value })} />
                        </Field>
                        <Field label="Sales end" error={error('sales_end_at')}>
                            <Input type="datetime-local" value={d.sales_end_at} onChange={(e) => set({ sales_end_at: e.target.value })} />
                        </Field>
                    </div>
                )}
            </div>
        </div>
    );
}

/** Small live ticket in the tier's colours, so organizers see what buyers will get. */
export function TicketPreview({ draft: d, currency, className }: { draft: TicketTypeDraft; currency: string; className?: string }) {
    const tier = tierMeta(d.tier);
    const Icon = tier.icon;
    const price = d.kind === 'free' ? 'Free' : d.price_major ? money(Math.round(parseFloat(d.price_major) * 100), currency) : `${currency} —`;

    return (
        <div className={cn('flex overflow-hidden rounded-2xl border border-tint/[0.08] bg-card shadow-[var(--shadow-card)]', className)} aria-label="Ticket preview">
            <div className="min-w-0 flex-1 p-4">
                <p className="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Live preview</p>
                <p className="mt-1 truncate text-lg font-extrabold tracking-tight text-ink">{d.name.trim() || 'Untitled ticket'}</p>
                <p className="mt-0.5 text-sm font-semibold text-slate-600">{price}</p>
                <div className="mt-2 flex flex-wrap gap-1.5">
                    <span className="rounded-full bg-tint/[0.05] px-2 py-0.5 text-[11px] font-semibold text-slate-500">
                        {d.quantity_total ? `${d.quantity_total} available` : 'Unlimited'}
                    </span>
                    {d.perks.length > 0 && (
                        <span className={cn('rounded-full px-2 py-0.5 text-[11px] font-semibold', tier.chip)}>
                            {d.perks.length} {d.perks.length === 1 ? 'perk' : 'perks'}
                        </span>
                    )}
                    {d.status !== 'active' && (
                        <span className="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-700">{d.status === 'paused' ? 'Paused' : 'Hidden'}</span>
                    )}
                </div>
            </div>
            <div className="relative flex w-28 shrink-0 flex-col items-center justify-center gap-1 border-l-2 border-dashed border-white/40 text-white" style={{ background: tier.swatch }}>
                <span className="absolute -left-2 -top-2 h-4 w-4 rounded-full bg-card" />
                <span className="absolute -bottom-2 -left-2 h-4 w-4 rounded-full bg-card" />
                <Icon className="h-6 w-6 drop-shadow" strokeWidth={2.2} />
                <span className="max-w-[6rem] truncate text-sm font-black uppercase tracking-wide drop-shadow">
                    {d.tier === 'other' ? d.name.trim() || 'Other' : tier.label}
                </span>
            </div>
        </div>
    );
}

function Section({ icon: Icon, title, hint, children }: { icon: typeof Tag; title: string; hint?: string; children: ReactNode }) {
    return (
        <section>
            <div className="mb-2.5 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                <h3 className="flex shrink-0 items-center gap-2 whitespace-nowrap text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                    <Icon className="h-3.5 w-3.5" /> {title}
                </h3>
                {hint && <span className="text-xs text-slate-400">{hint}</span>}
            </div>
            {children}
        </section>
    );
}

function Segmented<V extends string>({ value, onChange, options, className }: { value: V; onChange: (v: V) => void; options: { value: V; label: string }[]; className?: string }) {
    return (
        <div role="radiogroup" className={cn('grid h-12 auto-cols-fr grid-flow-col rounded-2xl border border-tint/10 bg-tint/[0.04] p-1', className)}>
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    role="radio"
                    aria-checked={value === o.value}
                    onClick={() => onChange(o.value)}
                    className={cn(
                        'rounded-xl text-sm font-semibold transition-all',
                        value === o.value ? 'bg-card text-ink shadow-[0_2px_8px_-2px_rgb(13_16_32_/_0.2)]' : 'text-slate-500 hover:text-ink',
                    )}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}

function Stepper({ value, min, max, onChange, className }: { value: number; min: number; max: number; onChange: (n: number) => void; className?: string }) {
    const clamp = (n: number) => Math.min(max, Math.max(min, Number.isNaN(n) ? min : n));

    return (
        <div className={cn('flex h-12 items-center rounded-2xl border border-tint/10 bg-tint/[0.04] p-1', className)}>
            <button type="button" onClick={() => onChange(clamp(value - 1))} disabled={value <= min} aria-label="Decrease" className="grid h-10 w-10 place-items-center rounded-xl text-ink transition hover:bg-tint/[0.06] disabled:opacity-30">
                <Minus className="h-4 w-4" />
            </button>
            <input
                type="number"
                min={min}
                max={max}
                value={value}
                onChange={(e) => onChange(clamp(parseInt(e.target.value, 10)))}
                aria-label="Max per order"
                className="h-full min-w-0 flex-1 bg-transparent text-center text-[15px] font-bold text-ink [appearance:textfield] focus:outline-none [&::-webkit-inner-spin-button]:appearance-none"
            />
            <button type="button" onClick={() => onChange(clamp(value + 1))} disabled={value >= max} aria-label="Increase" className="grid h-10 w-10 place-items-center rounded-xl text-ink transition hover:bg-tint/[0.06] disabled:opacity-30">
                <Plus className="h-4 w-4" />
            </button>
        </div>
    );
}

function Toggle({ label, checked, onChange }: { label: string; checked: boolean; onChange: (checked: boolean) => void }) {
    return (
        <button type="button" role="switch" aria-checked={checked} onClick={() => onChange(!checked)} className="inline-flex items-center gap-2 text-xs font-semibold text-slate-500">
            {label}
            <span className={cn('relative h-5 w-9 rounded-full transition-colors', checked ? 'bg-brand-500' : 'bg-tint/15')}>
                <span className={cn('absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all', checked ? 'left-[1.125rem]' : 'left-0.5')} />
            </span>
        </button>
    );
}
