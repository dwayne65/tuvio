import { categoryIcon } from '@/Components/CategoryIcon';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Cat {
    id: number;
    name: string;
    slug: string;
    icon: string | null;
    color: string | null;
    description: string | null;
    events_count: number;
}

export default function Categories({ categories }: PageProps<{ categories: Cat[] }>) {
    return (
        <PublicLayout>
            <Head title="Categories" />
            <section className="container-page py-14">
                <h1 className="text-3xl font-extrabold tracking-tight text-ink">Event categories</h1>
                <p className="mt-2 text-slate-500">Explore events across every kind of experience.</p>

                <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {categories.map((c) => {
                        const Icon = categoryIcon(c.icon);
                        return (
                            <Link
                                key={c.id}
                                href={`/events?category=${c.slug}`}
                                className="group rounded-2xl border border-slate-200/80 bg-card p-6 shadow-[var(--shadow-card)] transition-colors hover:border-brand-300"
                            >
                                <span
                                    className="grid h-12 w-12 place-items-center rounded-xl text-white"
                                    style={{ backgroundColor: c.color ?? '#2f7dff' }}
                                >
                                    <Icon className="h-6 w-6" />
                                </span>
                                <h2 className="mt-4 text-lg font-bold text-ink group-hover:text-brand-600">{c.name}</h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    {c.events_count} upcoming event{c.events_count === 1 ? '' : 's'}
                                </p>
                            </Link>
                        );
                    })}
                </div>
            </section>
        </PublicLayout>
    );
}

