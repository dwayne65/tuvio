import { TiltCard } from '@/Components/motion/TiltCard';
import { cn } from '@/lib/utils';
import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

/** Premium KPI tile: glass surface, 3D tilt, gradient icon chip. */
export function StatCard({
    icon: Icon,
    label,
    value,
    hint,
    tone = 'brand',
}: {
    icon: LucideIcon;
    label: string;
    value: ReactNode;
    hint?: string;
    tone?: 'brand' | 'emerald' | 'amber' | 'blue';
}) {
    const chip: Record<string, string> = {
        brand: 'from-brand-500 to-brand-700',
        emerald: 'from-emerald-400 to-emerald-600',
        amber: 'from-amber-400 to-amber-600',
        blue: 'from-sky-400 to-blue-600',
    };

    return (
        <TiltCard className="h-full rounded-[1.5rem]">
            <div className="relative h-full overflow-hidden rounded-[1.5rem] border border-tint/[0.07] bg-card p-4 shadow-[var(--shadow-card)] sm:p-5">
                <div className="pointer-events-none absolute -right-6 -top-8 h-24 w-24 rounded-full bg-brand-500/10 blur-2xl" />
                <span className={cn('grid h-11 w-11 place-items-center rounded-full bg-gradient-to-br text-white', chip[tone])}>
                    <Icon className="h-5 w-5" />
                </span>
                <p className="mt-4 break-words text-xl font-extrabold tracking-tight text-ink sm:text-2xl">{value}</p>
                <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</p>
                {hint && <p className="mt-1 text-xs text-slate-400">{hint}</p>}
            </div>
        </TiltCard>
    );
}
