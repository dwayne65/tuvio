import { Button } from '@/Components/ui/Button';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { motion } from 'framer-motion';
import { Check, Download, Lock, Mail, Ticket, X } from 'lucide-react';
import { useState } from 'react';

interface Order {
    reference: string;
    status: string;
    total_minor: number;
    currency: string;
    buyer_email: string;
    can_retry: boolean;
    payment_method: 'mobile_money' | 'card' | null;
    reserved_until: string | null;
    event: { title: string; slug: string };
    tickets: { code: string; type: string; attendee_name: string | null; download_url: string }[];
}

export default function Complete({ order }: PageProps<{ order: Order }>) {
    const { flash, auth } = usePage<PageProps>().props;
    const paid = order.status === 'paid';
    const [retrying, setRetrying] = useState(false);
    const heldUntil = order.reserved_until
        ? new Date(order.reserved_until).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : null;

    function retryPayment() {
        router.post(
            `/checkout/${order.reference}/pay`,
            {},
            { onStart: () => setRetrying(true), onFinish: () => setRetrying(false) },
        );
    }

    return (
        <PublicLayout>
            <Head title="Order complete" />
            <div className="container-page max-w-xl pb-10 pt-6 text-center md:py-16">
                <motion.div
                    initial={{ scale: 0.6, opacity: 0 }}
                    animate={{ scale: 1, opacity: 1 }}
                    transition={{ type: 'spring', stiffness: 260, damping: 18 }}
                    className={`mx-auto grid h-20 w-20 place-items-center rounded-full ${paid ? 'bg-brand-500 glow-blue' : 'bg-rose-500/20'}`}
                >
                    {paid ? <Check className="h-10 w-10 text-white" strokeWidth={3} /> : <X className="h-10 w-10 text-rose-600 dark:text-rose-300" strokeWidth={3} />}
                </motion.div>

                <h1 className="mt-6 text-[2.2rem] font-extrabold tracking-[-0.045em] text-ink">
                    {paid ? (
                        <>
                            You’re <span className="font-display font-normal italic tracking-normal text-gradient">going!</span>
                        </>
                    ) : (
                        'Payment not completed'
                    )}
                </h1>
                <p className="mt-2 text-slate-500">
                    {paid ? `Your tickets for ${order.event.title} are confirmed.` : flash.error || 'Your payment could not be verified. Please try again.'}
                </p>
                <p className="mt-1 font-mono text-xs text-slate-400">Order {order.reference}</p>

                {paid && order.tickets.length > 0 && (
                    <>
                        <p className="mx-auto mt-6 flex items-center justify-center gap-2 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700">
                            <Mail className="h-4 w-4 shrink-0" />
                            <span>
                                We’ve emailed your tickets to <strong>{order.buyer_email}</strong>
                            </span>
                        </p>

                        <div className="mt-6 space-y-3 text-left">
                            {order.tickets.map((t, i) => (
                                <motion.div
                                    key={t.code}
                                    initial={{ opacity: 0, y: 12 }}
                                    animate={{ opacity: 1, y: 0 }}
                                    transition={{ delay: 0.15 + i * 0.06 }}
                                    className="ticket-notch flex items-center gap-3 overflow-hidden rounded-[1.4rem] border border-tint/10 bg-gradient-to-r from-[#1a1d26] to-brand-900 p-4"
                                >
                                    <span className="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-500/20 text-brand-300">
                                        <Ticket className="h-5 w-5" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-white">{t.type} pass</p>
                                        <p className="truncate font-mono text-xs text-white/50">{t.code}</p>
                                    </div>
                                    <a
                                        href={t.download_url}
                                        className="ml-auto inline-flex shrink-0 items-center gap-1.5 rounded-full bg-white px-3.5 py-2 text-xs font-bold text-[#0b0d12] active:scale-95"
                                    >
                                        <Download className="h-4 w-4" /> PDF
                                    </a>
                                </motion.div>
                            ))}
                        </div>
                    </>
                )}

                {!paid && order.can_retry && heldUntil && (
                    <p className="mt-4 text-sm text-slate-500">Your tickets are held for you until {heldUntil}.</p>
                )}

                <div className="mt-8 flex flex-col justify-center gap-2.5 sm:flex-row">
                    {paid && auth.user && <Button href="/my-account/tickets" size="lg">View my tickets</Button>}
                    {!paid && order.can_retry && (
                        <Button onClick={() => retryPayment()} disabled={retrying} size="lg">
                            <Lock className="h-4 w-4" /> Try payment again
                        </Button>
                    )}
                    <Button href={`/events/${order.event.slug}`} variant="outline" size="lg">
                        Back to event
                    </Button>
                </div>
                <p className="mt-6 text-center text-sm text-slate-500">
                    Problem with your order?{' '}
                    <a href={`/contact?order=${order.reference}`} className="font-semibold text-brand-300 hover:underline">Contact support</a>
                </p>
            </div>
        </PublicLayout>
    );
}
