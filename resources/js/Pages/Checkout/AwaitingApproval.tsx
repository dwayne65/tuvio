import { Button } from '@/Components/ui/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, usePoll } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { ChevronLeft, Loader2, Smartphone } from 'lucide-react';
import { useEffect, useState } from 'react';

interface PendingOrder {
    reference: string;
    total_minor: number;
    currency: string;
    event_title: string;
    phone_hint: string;
    reserved_until: string | null;
}

function useCountdown(until: string | null): string | null {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, []);

    if (!until) {
        return null;
    }
    const seconds = Math.max(0, Math.floor((new Date(until).getTime() - now) / 1000));

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

export default function AwaitingApproval({ order }: PageProps<{ order: PendingOrder }>) {
    // The server re-checks Paytota on every reload and redirects once the payment settles.
    usePoll(4000);
    const remaining = useCountdown(order.reserved_until);

    return (
        <PublicLayout hideTabBar>
            <Head title="Approve on your phone" />
            <div className="container-page max-w-md pb-10 pt-6 text-center md:py-16">
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

                <h1 className="mt-6 text-[2rem] font-extrabold tracking-[-0.045em] text-ink">Check your phone</h1>
                <p className="mt-2 text-slate-500">
                    We sent a Mobile Money prompt to <strong className="text-ink">{order.phone_hint}</strong>. Enter your PIN to pay{' '}
                    <strong className="text-ink">{money(order.total_minor, order.currency)}</strong> for {order.event_title}.
                </p>

                <div className="mt-6 flex items-center justify-center gap-2 rounded-2xl border border-tint/[0.08] bg-card/80 px-4 py-3 text-sm text-slate-500">
                    <Loader2 className="h-4 w-4 animate-spin text-brand-500" />
                    Waiting for your approval…
                    {remaining && <span className="font-mono text-ink">{remaining}</span>}
                </div>

                <ul className="mt-6 space-y-1.5 text-left text-sm text-slate-500">
                    <li>• No prompt? Check that the number can receive Mobile Money and has enough balance.</li>
                    <li>• Keep this page open. It moves on by itself once you approve.</li>
                </ul>

                <div className="mt-8 flex flex-col justify-center gap-2.5 sm:flex-row">
                    <Button href="/checkout" variant="outline" size="lg">
                        <ChevronLeft className="h-4 w-4" /> Change number or payment method
                    </Button>
                </div>
                <p className="mt-6 font-mono text-xs text-slate-400">Order {order.reference}</p>
            </div>
        </PublicLayout>
    );
}
