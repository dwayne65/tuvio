import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Format integer minor units as a human currency string. */
export function money(minor: number, currency = 'UGX'): string {
    const major = minor / 100;
    try {
        return new Intl.NumberFormat('en-UG', {
            style: 'currency',
            currency,
            maximumFractionDigits: major % 1 === 0 ? 0 : 2,
        }).format(major);
    } catch {
        return `${currency} ${major.toLocaleString()}`;
    }
}

export function formatDate(iso: string, opts?: Intl.DateTimeFormatOptions): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        ...opts,
    });
}

export function formatDateTime(iso: string): string {
    return new Date(iso).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}
