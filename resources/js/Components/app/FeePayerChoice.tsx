import { cn, money } from '@/lib/utils';
import { Check, Ticket, Wallet } from 'lucide-react';

export type FeePayer = 'organizer' | 'buyer';

/** What a ticket costs the buyer and earns the organizer under each fee option. */
export function feeSplit(priceMinor: number, commissionBps: number, payer: FeePayer) {
    const fee = Math.round((priceMinor * commissionBps) / 10000);

    return payer === 'buyer'
        ? { buyerPays: priceMinor + fee, organizerGets: priceMinor, fee }
        : { buyerPays: priceMinor, organizerGets: priceMinor - fee, fee };
}

const options: { value: FeePayer; label: string; hint: string; icon: typeof Wallet }[] = [
    { value: 'organizer', label: 'I pay the fee', hint: 'Buyers pay the ticket price. The fee comes out of your earnings.', icon: Wallet },
    { value: 'buyer', label: 'Buyers pay the fee', hint: 'A booking fee is added at checkout. You keep the full ticket price.', icon: Ticket },
];

export default function FeePayerChoice({ value, onChange, commissionBps, currency, examplePriceMinor = 5000000 }: {
    value: FeePayer;
    onChange: (value: FeePayer) => void;
    commissionBps: number;
    currency: string;
    /** Ticket price used for the worked example, in minor units. */
    examplePriceMinor?: number;
}) {
    const rate = `${commissionBps / 100}%`;
    const example = feeSplit(examplePriceMinor, commissionBps, value);

    return (
        <fieldset>
            <legend className="text-sm font-semibold text-ink">Who pays the {rate} Tuvio fee?</legend>
            <p className="mt-0.5 text-xs text-slate-500">Mobile Money and card charges are included. There are no other fees.</p>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                {options.map(({ value: option, label, hint, icon: Icon }) => {
                    const selected = value === option;

                    return (
                        <label
                            key={option}
                            className={cn(
                                'relative flex cursor-pointer items-start gap-3 rounded-2xl border p-4 transition-colors',
                                selected ? 'border-brand-500 bg-brand-500/[0.07] ring-1 ring-brand-500' : 'border-tint/10 hover:border-tint/20',
                            )}
                        >
                            <input type="radio" name="fee_payer" value={option} checked={selected} onChange={() => onChange(option)} className="sr-only" />
                            <span className={cn('grid h-9 w-9 shrink-0 place-items-center rounded-full', selected ? 'bg-brand-500 text-white' : 'bg-tint/[0.06] text-slate-500')}>
                                <Icon className="h-4 w-4" />
                            </span>
                            <span className="min-w-0 pr-5">
                                <span className="block font-semibold text-ink">{label}</span>
                                <span className="block text-xs text-slate-500">{hint}</span>
                            </span>
                            {selected && <Check className="absolute right-3 top-3 h-4 w-4 text-brand-500" />}
                        </label>
                    );
                })}
            </div>
            <p className="mt-3 rounded-2xl bg-tint/[0.04] px-4 py-3 text-sm text-slate-600">
                On a {money(examplePriceMinor, currency)} ticket the buyer pays <strong className="text-ink">{money(example.buyerPays, currency)}</strong> and
                you receive <strong className="text-ink">{money(example.organizerGets, currency)}</strong>.
            </p>
        </fieldset>
    );
}
