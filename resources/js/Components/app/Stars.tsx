import { cn } from '@/lib/utils';
import { Star } from 'lucide-react';

/** Read-only star rating, or an input when `onChange` is given. */
export function Stars({ value, onChange, size = 'sm', className }: { value: number; onChange?: (v: number) => void; size?: 'sm' | 'lg'; className?: string }) {
    const icon = size === 'lg' ? 'h-8 w-8' : 'h-4 w-4';

    return (
        <div className={cn('flex items-center gap-0.5', className)} role={onChange ? 'radiogroup' : 'img'} aria-label={onChange ? 'Rating' : `${value} out of 5 stars`}>
            {[1, 2, 3, 4, 5].map((n) => {
                const filled = n <= Math.round(value);
                const star = <Star className={cn(icon, filled ? 'fill-amber-400 text-amber-400' : 'fill-transparent text-slate-300')} />;

                return onChange ? (
                    <button
                        key={n}
                        type="button"
                        role="radio"
                        aria-checked={n === value}
                        aria-label={`${n} star${n > 1 ? 's' : ''}`}
                        onClick={() => onChange(n)}
                        className="rounded-full p-0.5 transition-transform active:scale-90"
                    >
                        {star}
                    </button>
                ) : (
                    <span key={n}>{star}</span>
                );
            })}
        </div>
    );
}
