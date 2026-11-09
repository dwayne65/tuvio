import { Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { ReactNode } from 'react';

/** Page heading for the app layouts: optional back link, subtitle and actions. */
export function PageTitle({
    title,
    subtitle,
    actions,
    back,
}: {
    title: ReactNode;
    subtitle?: ReactNode;
    actions?: ReactNode;
    back?: { href: string; label: string };
}) {
    return (
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                {back && (
                    <Link href={back.href} className="mb-2 inline-flex items-center gap-1 text-sm font-semibold text-slate-500 hover:text-ink">
                        <ChevronLeft className="h-4 w-4" /> {back.label}
                    </Link>
                )}
                <h1 className="text-[1.75rem] font-extrabold leading-tight tracking-[-0.045em] text-ink lg:text-[2.1rem]">{title}</h1>
                {subtitle && <p className="mt-1 text-[15px] text-slate-500">{subtitle}</p>}
            </div>
            {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
        </div>
    );
}
