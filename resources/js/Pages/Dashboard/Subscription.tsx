import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Accent } from '@/Components/app/Kit';
import { cn } from '@/lib/utils';
import AppLayout from '@/Layouts/AppLayout';
import { formatDate, money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Check } from 'lucide-react';

interface Plan {
    id: number;
    name: string;
    description: string | null;
    price: { minor: number; currency: string };
    interval: string;
    trial_days: number;
    event_limit: number | null;
    features: string[];
}

interface Current {
    id: number;
    status: string;
    plan_id: number;
    plan_name: string;
    current_period_end: string | null;
    trial_ends_at: string | null;
    cancel_at_period_end: boolean;
    is_active: boolean;
}

const statusTone: Record<string, 'success' | 'warning' | 'danger' | 'neutral'> = {
    active: 'success',
    trialing: 'success',
    past_due: 'warning',
    incomplete: 'warning',
    canceled: 'danger',
    expired: 'danger',
};

export default function Subscription({ plans, current }: PageProps<{ plans: Plan[]; current: Current | null }>) {
    function subscribe(planId: number) {
        router.post('/dashboard/subscription', { plan_id: planId });
    }

    return (
        <AppLayout title={<>Your <Accent>plan</Accent></>} subtitle="Pick the plan that fits how often you host.">
            <Head title="Subscription" />

            {current && (
                <div className="mb-6 flex flex-col gap-4 rounded-[1.5rem] border border-tint/[0.07] bg-card p-5 shadow-[var(--shadow-card)] sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div>
                            <div className="flex items-center gap-3">
                                <h2 className="text-lg font-bold text-ink">{current.plan_name} plan</h2>
                                <Badge tone={statusTone[current.status] ?? 'neutral'}>{current.status}</Badge>
                            </div>
                            <p className="mt-1 text-sm text-slate-500">
                                {current.status === 'trialing' && current.trial_ends_at
                                    ? `Trial ends ${formatDate(current.trial_ends_at)}`
                                    : current.cancel_at_period_end && current.current_period_end
                                      ? `Cancels on ${formatDate(current.current_period_end)}`
                                      : current.current_period_end
                                        ? `Renews ${formatDate(current.current_period_end)}`
                                        : ''}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {current.cancel_at_period_end ? (
                                <Button variant="outline" onClick={() => router.post('/dashboard/subscription/resume')}>
                                    Resume
                                </Button>
                            ) : (
                                <Button variant="outline" onClick={() => router.post('/dashboard/subscription/cancel')}>
                                    Cancel plan
                                </Button>
                            )}
                        </div>
                </div>
            )}

            <div className="scroller -mx-4 flex gap-4 overflow-x-auto px-4 pb-2 pt-3 lg:mx-0 lg:grid lg:grid-cols-3 lg:gap-5 lg:overflow-visible lg:px-0">
                {plans.map((plan, i) => {
                    const isCurrent = current?.plan_id === plan.id && current.is_active;
                    return (
                        <div
                            key={plan.id}
                            className={cn(
                                'relative flex w-[82vw] max-w-sm shrink-0 flex-col rounded-[1.75rem] border p-7 shadow-[var(--shadow-card)] lg:w-auto lg:max-w-none',
                                isCurrent ? 'border-emerald-500/60 bg-card' : i === 1 ? 'border-brand-500/60 bg-gradient-to-b from-brand-500/10 to-card' : 'border-tint/[0.08] bg-card',
                            )}
                        >
                            {i === 1 && !isCurrent && (
                                <span className="absolute right-6 top-6 rounded-full bg-brand-500 px-3 py-1 text-[11px] font-bold text-white">
                                    Popular
                                </span>
                            )}
                            <h3 className="text-lg font-bold text-ink">{plan.name}</h3>
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
                                        <span className="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded-full bg-brand-500/15">
                                            <Check className="h-3 w-3 text-brand-600" />
                                        </span>
                                        {f}
                                    </li>
                                ))}
                            </ul>
                            <Button
                                onClick={() => subscribe(plan.id)}
                                variant={isCurrent ? 'outline' : i === 1 ? 'primary' : 'outline'}
                                className="mt-8 w-full"
                                disabled={isCurrent}
                            >
                                {isCurrent ? 'Current plan' : current ? 'Switch to this plan' : 'Choose plan'}
                            </Button>
                        </div>
                    );
                })}
            </div>
        </AppLayout>
    );
}
