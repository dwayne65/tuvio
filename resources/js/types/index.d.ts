import { PageProps as InertiaPageProps } from '@inertiajs/core';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar_path: string | null;
    is_admin: boolean;
}

export interface Category {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
}

export interface Money {
    minor: number;
    currency: string;
    formatted: string;
}

export interface EventSummary {
    id: number;
    title: string;
    slug: string;
    cover_image_path: string | null;
    start_at: string;
    venue_name: string | null;
    city: string | null;
    type: string;
    category: Category | null;
    organization: { id: number; name: string; slug: string } | null;
    price_from: Money | null;
    is_free: boolean;
    is_featured: boolean;
}

export interface Plan {
    id: number;
    name: string;
    slug: string;
    price: Money;
    interval: string;
    trial_days: number;
    event_limit: number | null;
    features: string[];
}

export type SharedProps = {
    auth: {
        user: User | null;
        organizations?: { id: number; name: string; slug: string; role: string }[];
        currentOrganization?: { id: number; name: string; slug: string } | null;
    };
    flash: { success: string | null; error: string | null };
    app: { name: string; currency: string; demo_mode?: boolean };
    notifications: { unread: number };
};

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> =
    T & SharedProps & InertiaPageProps;
