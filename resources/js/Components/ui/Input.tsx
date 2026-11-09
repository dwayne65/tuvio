import { cn } from '@/lib/utils';
import { forwardRef, InputHTMLAttributes, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';

const fieldBase =
    'h-12 w-full rounded-2xl border border-tint/10 bg-tint/[0.04] px-4 text-[15px] text-ink placeholder:text-slate-400 transition-colors focus:border-brand-500 focus:bg-tint/[0.06] focus:outline-none focus:ring-4 focus:ring-brand-500/15';

export const Input = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(
    ({ className, ...props }, ref) => (
        <input ref={ref} className={cn(fieldBase, className)} {...props} />
    ),
);
Input.displayName = 'Input';

export const Select = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(
    ({ className, ...props }, ref) => (
        <select ref={ref} className={cn(fieldBase, 'pr-8', className)} {...props} />
    ),
);
Select.displayName = 'Select';

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(
    ({ className, ...props }, ref) => (
        <textarea ref={ref} className={cn(fieldBase, 'h-auto min-h-28 py-3 leading-relaxed', className)} {...props} />
    ),
);
Textarea.displayName = 'Textarea';

export function Field({
    label,
    error,
    children,
}: {
    label?: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block space-y-1.5">
            {label && <span className="text-sm font-medium text-ink">{label}</span>}
            {children}
            {error && <span className="block text-sm text-danger">{error}</span>}
        </label>
    );
}
