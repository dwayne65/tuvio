import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { usePage } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { CheckCircle2, X, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Toast {
    id: number;
    tone: 'success' | 'error';
    message: string;
}

/** Shows the server's one-time flash messages (success / error) as toasts. */
export function Toaster() {
    const { flash } = usePage<PageProps>().props;
    const [toasts, setToasts] = useState<Toast[]>([]);

    useEffect(() => {
        const incoming: Toast[] = [];
        if (flash?.success) {
            incoming.push({ id: Date.now(), tone: 'success', message: flash.success });
        }
        if (flash?.error) {
            incoming.push({ id: Date.now() + 1, tone: 'error', message: flash.error });
        }
        if (incoming.length === 0) {
            return;
        }
        setToasts((current) => [...current, ...incoming].slice(-3));
        const timer = window.setTimeout(() => {
            setToasts((current) => current.filter((t) => !incoming.some((i) => i.id === t.id)));
        }, 5000);
        return () => window.clearTimeout(timer);
    }, [flash?.success, flash?.error]);

    const dismiss = (id: number) => setToasts((current) => current.filter((t) => t.id !== id));

    return (
        <div
            aria-live="polite"
            className="pointer-events-none fixed inset-x-0 top-[max(0.75rem,env(safe-area-inset-top))] z-[80] flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-5 sm:top-5 sm:items-end"
        >
            <AnimatePresence>
                {toasts.map((t) => (
                    <motion.div
                        key={t.id}
                        layout
                        initial={{ opacity: 0, y: -16, scale: 0.96 }}
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        exit={{ opacity: 0, y: -12, scale: 0.96 }}
                        transition={{ type: 'spring', stiffness: 420, damping: 32 }}
                        role={t.tone === 'error' ? 'alert' : 'status'}
                        className="glass pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-2xl p-3.5 pr-2.5 shadow-premium"
                    >
                        <span
                            className={cn(
                                'grid h-8 w-8 shrink-0 place-items-center rounded-full',
                                t.tone === 'success' ? 'bg-emerald-500/15 text-emerald-600' : 'bg-rose-500/15 text-rose-600 dark:text-rose-300',
                            )}
                        >
                            {t.tone === 'success' ? <CheckCircle2 className="h-[18px] w-[18px]" /> : <XCircle className="h-[18px] w-[18px]" />}
                        </span>
                        <p className="flex-1 pt-1 text-sm font-medium text-ink">{t.message}</p>
                        <button onClick={() => dismiss(t.id)} aria-label="Dismiss" className="grid h-7 w-7 shrink-0 place-items-center rounded-full text-slate-400 hover:bg-tint/[0.06] hover:text-ink">
                            <X className="h-4 w-4" />
                        </button>
                    </motion.div>
                ))}
            </AnimatePresence>
        </div>
    );
}
