import { cn } from '@/lib/utils';
import { HTMLAttributes } from 'react';

type Tone = 'brand' | 'neutral' | 'success' | 'warning' | 'danger';

const tones: Record<Tone, string> = {
    brand: 'bg-brand-500/15 text-brand-300 ring-1 ring-inset ring-brand-400/20',
    neutral: 'bg-tint/[0.07] text-slate-600 ring-1 ring-inset ring-white/10',
    success: 'bg-emerald-500/15 text-emerald-700 ring-1 ring-inset ring-emerald-400/20',
    warning: 'bg-amber-500/15 text-amber-700 ring-1 ring-inset ring-amber-400/20',
    danger: 'bg-rose-500/15 text-rose-700 dark:text-rose-300 ring-1 ring-inset ring-rose-400/20',
};

interface Props extends HTMLAttributes<HTMLSpanElement> {
    tone?: Tone;
}

export function Badge({ tone = 'neutral', className, ...props }: Props) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold backdrop-blur',
                tones[tone],
                className,
            )}
            {...props}
        />
    );
}
