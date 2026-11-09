import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ButtonHTMLAttributes, forwardRef, ReactNode } from 'react';

type Variant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

const base =
    'inline-flex items-center justify-center gap-2 rounded-full font-semibold whitespace-nowrap transition-all duration-200 active:scale-[0.97] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-400/60 focus-visible:ring-offset-2 focus-visible:ring-offset-canvas disabled:pointer-events-none disabled:opacity-50';

const variants: Record<Variant, string> = {
    primary: 'bg-brand-500 text-white glow-blue hover:-translate-y-0.5 hover:bg-brand-400',
    secondary: 'bg-ink text-canvas hover:-translate-y-0.5 hover:opacity-90',
    outline: 'border border-tint/12 bg-tint/[0.04] text-ink backdrop-blur hover:-translate-y-0.5 hover:border-tint/20 hover:bg-tint/[0.08]',
    ghost: 'text-ink hover:bg-tint/[0.06]',
    danger: 'bg-danger text-white hover:bg-rose-500',
};

const sizes: Record<Size, string> = {
    sm: 'h-9 px-4 text-sm',
    md: 'h-11 px-5 text-sm',
    lg: 'h-13 px-7 text-base',
};

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    href?: string;
    children: ReactNode;
}

export const Button = forwardRef<HTMLButtonElement, Props>(
    ({ variant = 'primary', size = 'md', href, className, children, ...props }, ref) => {
        const classes = cn(base, variants[variant], sizes[size], className);

        if (href) {
            return (
                <Link href={href} className={classes}>
                    {children}
                </Link>
            );
        }

        return (
            <button ref={ref} className={classes} {...props}>
                {children}
            </button>
        );
    },
);
Button.displayName = 'Button';
