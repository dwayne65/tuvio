import { Ticket } from 'lucide-react';
import { cn } from '@/lib/utils';

/** `light` is kept for call-site compatibility; the mark is always drawn for the dark canvas. */
export function Logo({ className, light: _light = false }: { className?: string; light?: boolean }) {
    return (
        <span className={cn('inline-flex items-center gap-2.5 font-extrabold tracking-tight', className)}>
            <span className="relative grid h-9 w-9 -rotate-6 place-items-center overflow-hidden rounded-[0.8rem] bg-gradient-to-br from-brand-400 to-brand-700 text-white glow-blue">
                <span className="absolute inset-x-0 top-0 h-1/2 bg-white/15" />
                <Ticket className="relative h-[18px] w-[18px] rotate-6" strokeWidth={2.4} />
            </span>
            <span className="text-[1.1rem] tracking-[-0.03em] text-ink">
                Ticket<span className="font-display text-[1.3rem] font-normal italic tracking-normal text-brand-300">Flow</span>
            </span>
        </span>
    );
}
