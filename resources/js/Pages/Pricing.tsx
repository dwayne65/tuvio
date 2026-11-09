import { Button } from '@/Components/ui/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { Check } from 'lucide-react';

interface Plan {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    price: { minor: number; currency: string };
    interval: string;
    trial_days: number;
    event_limit: number | null;
    ticket_limit: number | null;
    features: string[];
}

export default function Pricing({ plans }: PageProps<{ plans: Plan[] }>) {
    return (
        <PublicLayout>
            <Head title="Pricing" />
            <section className="container-page py-16 text-center">
                <h1 className="text-4xl font-extrabold tracking-tight text-ink">Simple, transparent pricing</h1>
                <p className="mx-auto mt-3 max-w-xl text-slate-500">
                    Start free, upgrade as you grow. All plans include secure payments, QR tickets and the check-in app.
                </p>

                <div className="mx-auto mt-12 grid max-w-5xl gap-6 lg:grid-cols-3">
                    {plans.map((plan, i) => (
                        <div
                            key={plan.id}
                            className={`relative flex flex-col rounded-2xl border bg-card p-8 text-left shadow-[var(--shadow-card)] ${
                                i === 1 ? 'border-brand-500 ring-2 ring-brand-500/20' : 'border-slate-200'
                            }`}
                        >
                            {i === 1 && (
                                <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-brand-500 px-3 py-1 text-xs font-semibold text-white">
                                    Most popular
                                </span>
                            )}
                            <h3 className="text-lg font-bold text-ink">{plan.name}</h3>
                            {plan.description && <p className="mt-1 text-sm text-slate-500">{plan.description}</p>}
                            <div className="mt-4 flex items-baseline gap-1">
                                <span className="text-4xl font-extrabold text-ink">
                                    {plan.price.minor === 0 ? 'Free' : money(plan.price.minor, plan.price.currency)}
                                </span>
                                {plan.price.minor > 0 && <span className="text-sm text-slate-500">/{plan.interval}</span>}
                            </div>
                            {plan.trial_days > 0 && (
                                <p className="mt-1 text-sm font-medium text-brand-600">{plan.trial_days}-day free trial</p>
                            )}
                            <ul className="mt-6 flex-1 space-y-3">
                                {plan.features.map((f) => (
                                    <li key={f} className="flex items-start gap-2 text-sm text-slate-600">
                                        <Check className="mt-0.5 h-4 w-4 shrink-0 text-brand-500" />
                                        {f}
                                    </li>
                                ))}
                            </ul>
                            <Button href="/register" variant={i === 1 ? 'primary' : 'outline'} className="mt-8 w-full">
                                Get started
                            </Button>
                        </div>
                    ))}
                </div>

                <p className="mt-10 text-sm text-slate-400">
                    Plus a small commission per paid ticket. No hidden fees.
                </p>
            </section>
        </PublicLayout>
    );
}
