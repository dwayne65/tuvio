import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';

/** Rounded panel with an optional header row. */
export function Panel({
    title,
    subtitle,
    action,
    className,
    bodyClassName,
    children,
}: {
    title?: ReactNode;
    subtitle?: ReactNode;
    action?: ReactNode;
    className?: string;
    bodyClassName?: string;
    children: ReactNode;
}) {
    return (
        <section className={cn('rounded-[1.5rem] border border-tint/[0.07] bg-card shadow-[var(--shadow-card)]', className)}>
            {(title || action) && (
                <header className="flex items-start justify-between gap-3 px-5 pt-5 sm:px-6 sm:pt-6">
                    <div className="min-w-0">
                        {title && <h2 className="text-base font-bold text-ink sm:text-lg">{title}</h2>}
                        {subtitle && <p className="mt-0.5 text-sm text-slate-500">{subtitle}</p>}
                    </div>
                    {action && <div className="shrink-0">{action}</div>}
                </header>
            )}
            <div className={cn('p-5 sm:p-6', bodyClassName)}>{children}</div>
        </section>
    );
}

export function EmptyState({ icon: Icon, title, body, action }: { icon: LucideIcon; title: string; body?: string; action?: ReactNode }) {
    return (
        <div className="flex flex-col items-center rounded-[1.5rem] border border-dashed border-tint/[0.12] bg-card/60 px-6 py-14 text-center">
            <span className="grid h-14 w-14 place-items-center rounded-full bg-brand-500/10 text-brand-300 ring-1 ring-inset ring-brand-400/20">
                <Icon className="h-6 w-6" />
            </span>
            <h3 className="mt-4 text-base font-bold text-ink">{title}</h3>
            {body && <p className="mt-1 max-w-sm text-sm text-slate-500">{body}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

/** Horizontally scrollable chip filter (single choice). */
export function FilterChips<V extends string>({
    options,
    value,
    onChange,
    className,
}: {
    options: { value: V; label: string }[];
    value: V;
    onChange: (value: V) => void;
    className?: string;
}) {
    return (
        <div className={cn('scroller -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0', className)}>
            {options.map((o) => (
                <button
                    key={o.value}
                    type="button"
                    onClick={() => onChange(o.value)}
                    aria-pressed={value === o.value}
                    className={cn(
                        'inline-flex h-9 shrink-0 items-center rounded-full border px-4 text-sm font-semibold transition-colors',
                        value === o.value
                            ? 'border-brand-500 bg-brand-500 text-white'
                            : 'border-tint/[0.08] bg-card text-slate-600 hover:border-tint/20 hover:text-ink',
                    )}
                >
                    {o.label}
                </button>
            ))}
        </div>
    );
}

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

/** Laravel paginator links → compact previous / page / next controls. */
export function Pagination({ links }: { links?: PageLink[] }) {
    if (!links || links.length <= 3) {
        return null;
    }
    const prev = links[0];
    const next = links[links.length - 1];
    const pages = links.slice(1, -1);
    const current = pages.findIndex((l) => l.active) + 1;

    return (
        <nav aria-label="Pagination" className="mt-5 flex items-center justify-between gap-3">
            <PageButton link={prev} label="Previous">
                <ChevronLeft className="h-4 w-4" /> <span className="hidden sm:inline">Previous</span>
            </PageButton>
            <div className="hidden items-center gap-1 sm:flex">
                {pages.map((l, i) =>
                    l.url ? (
                        <Link
                            key={i}
                            href={l.url}
                            preserveScroll
                            className={cn(
                                'grid h-9 min-w-9 place-items-center rounded-full px-3 text-sm font-semibold transition-colors',
                                l.active ? 'bg-brand-500 text-white' : 'text-slate-500 hover:bg-tint/[0.06] hover:text-ink',
                            )}
                        >
                            {l.label}
                        </Link>
                    ) : (
                        <span key={i} className="px-2 text-sm text-slate-400">…</span>
                    ),
                )}
            </div>
            <span className="text-sm font-medium text-slate-500 sm:hidden">
                Page {current} of {pages.length}
            </span>
            <PageButton link={next} label="Next">
                <span className="hidden sm:inline">Next</span> <ChevronRight className="h-4 w-4" />
            </PageButton>
        </nav>
    );
}

function PageButton({ link, label, children }: { link: PageLink; label: string; children: ReactNode }) {
    const cls = 'inline-flex h-10 items-center gap-1 rounded-full border border-tint/[0.08] bg-card px-4 text-sm font-semibold';
    return link.url ? (
        <Link href={link.url} preserveScroll aria-label={label} className={cn(cls, 'text-ink hover:border-tint/20')}>
            {children}
        </Link>
    ) : (
        <span aria-disabled="true" className={cn(cls, 'text-slate-400 opacity-60')}>
            {children}
        </span>
    );
}

/** Small icon + text cell used in list rows. */
export function Avatar({ name, className }: { name: string | null; className?: string }) {
    return (
        <span
            className={cn(
                'grid h-10 w-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-400 to-brand-700 text-sm font-bold text-white',
                className,
            )}
        >
            {(name ?? '?').trim().charAt(0).toUpperCase() || '?'}
        </span>
    );
}

/** A labelled value pair used on mobile cards. */
export function Meta({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="min-w-0">
            <p className="text-[10px] font-bold uppercase tracking-[0.14em] text-slate-400">{label}</p>
            <div className="mt-0.5 truncate text-sm font-semibold text-ink">{children}</div>
        </div>
    );
}

/** Pill-shaped secondary action that works for plain <a> links (PDFs, downloads). */
export function PillLink({ href, children, newTab }: { href: string; children: ReactNode; newTab?: boolean }) {
    return (
        <a
            href={href}
            {...(newTab ? { target: '_blank', rel: 'noopener' } : {})}
            className="inline-flex h-9 items-center gap-1.5 rounded-full border border-tint/[0.1] bg-card px-3.5 text-xs font-semibold text-ink transition-colors hover:border-brand-400 hover:text-brand-600"
        >
            {children}
        </a>
    );
}

/** Serif-italic accent word used in page titles, matching the public site. */
export function Accent({ children }: { children: ReactNode }) {
    return <span className="font-display font-normal italic tracking-normal text-gradient">{children}</span>;
}

/** Time-of-day greeting in the visitor's local time. */
export function greeting(date: Date = new Date()): string {
    const h = date.getHours();
    if (h < 12) {
        return 'Good morning';
    }
    return h < 18 ? 'Good afternoon' : 'Good evening';
}
