import { cn } from '@/lib/utils';
import { HTMLAttributes } from 'react';

export function Card({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return (
        <div
            className={cn(
                'rounded-[1.5rem] border border-tint/[0.07] bg-card/80 shadow-[var(--shadow-card)] backdrop-blur-xl',
                className,
            )}
            {...props}
        />
    );
}

export function CardBody({ className, ...props }: HTMLAttributes<HTMLDivElement>) {
    return <div className={cn('p-5 sm:p-6', className)} {...props} />;
}
