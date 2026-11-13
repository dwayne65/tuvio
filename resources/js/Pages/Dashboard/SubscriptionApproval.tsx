import { Button } from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, usePoll } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ChevronLeft, Loader2, Smartphone } from 'lucide-react';

interface PendingInvoice {
    number: string;
    plan_name: string;
    amount_minor: number;
    currency: string;
    phone_hint: string;
}

export default function SubscriptionApproval({ invoice }: PageProps<{ invoice: PendingInvoice }>) {
    // The server re-checks Paytota on every reload and redirects once the payment settles.
    usePoll(4000);

    return (
        <AppLayout title="Approve on your phone">
            <Head title="Approve on your phone" />
            <div className="mx-auto max-w-md py-6 text-center">
                <div className="relative mx-auto grid h-24 w-24 place-items-center">
                    <motion.span
                        className="absolute inset-0 rounded-full bg-brand-500/25"
                        animate={{ scale: [1, 1.35, 1], opacity: [0.7, 0, 0.7] }}
                        transition={{ duration: 2, repeat: Infinity, ease: 'easeOut' }}
                    />
                    <span className="relative grid h-20 w-20 place-items-center rounded-full bg-brand-500 glow-blue">
                        <Smartphone className="h-9 w-9 text-white" />
                    </span>
                </div>

                <h2 className="mt-6 text-[2rem] font-extrabold tracking-[-0.045em] text-ink">Check your phone</h2>
                <p className="mt-2 text-slate-500">
                    We sent a Mobile Money prompt to <strong className="text-ink">{invoice.phone_hint}</strong>. Enter your PIN to pay{' '}
                    <strong className="text-ink">{money(invoice.amount_minor, invoice.currency)}</strong> for the {invoice.plan_name} plan.
                </p>

                <div className="mt-6 flex items-center justify-center gap-2 rounded-2xl border border-tint/[0.08] bg-card/80 px-4 py-3 text-sm text-slate-500">
                    <Loader2 className="h-4 w-4 animate-spin text-brand-500" />
                    Waiting for your approval…
                </div>

                <ul className="mt-6 space-y-1.5 text-left text-sm text-slate-500">
                    <li>• No prompt? Check that the number can receive Mobile Money and has enough balance.</li>
                    <li>• Keep this page open. Your plan switches on by itself once you approve.</li>
                </ul>

                <Button href="/dashboard/subscription" variant="outline" size="lg" className="mt-8">
                    <ChevronLeft className="h-4 w-4" /> Change number or payment method
                </Button>
                <p className="mt-6 font-mono text-xs text-slate-400">Invoice {invoice.number}</p>
            </div>
        </AppLayout>
    );
}
