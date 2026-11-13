import { cn } from '@/lib/utils';

type Variant = 'full' | 'mark' | 'tagline';

const sources: Record<Exclude<Variant, 'mark'>, { light: string; dark: string; ratio: string }> = {
    full: { light: '/brand/tuvio-logo.png', dark: '/brand/tuvio-logo-dark.png', ratio: 'aspect-[576/160]' },
    tagline: { light: '/brand/tuvio-logo-tagline.png', dark: '/brand/tuvio-logo-tagline-dark.png', ratio: 'aspect-[1195/320]' },
};

/**
 * TUVIO brand logo. `full` is the mark + wordmark, `tagline` adds "Life Happens Here.",
 * `mark` is the T-ticket icon alone. The wordmark swaps to a white version in night mode.
 * Size it with a height class, e.g. `h-8`.
 */
export function Logo({ variant = 'full', className }: { variant?: Variant; className?: string }) {
    if (variant === 'mark') {
        return <img src="/brand/tuvio-mark.png" alt="TUVIO" className={cn('h-9 w-auto select-none', className)} draggable={false} />;
    }

    const src = sources[variant];

    return (
        <span className={cn('relative inline-block h-9 select-none', src.ratio, className)}>
            <img src={src.light} alt="TUVIO" className="h-full w-full object-contain dark:hidden" draggable={false} />
            <img src={src.dark} alt="TUVIO" className="hidden h-full w-full object-contain dark:block" draggable={false} />
        </span>
    );
}

export const BRAND = { name: 'TUVIO', tagline: 'Life Happens Here.' } as const;
