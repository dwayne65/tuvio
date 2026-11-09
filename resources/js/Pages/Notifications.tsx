import { Accent, EmptyState } from '@/Components/app/Kit';
import { Reveal } from '@/Components/motion/Reveal';
import { Button } from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import { cn, formatDateTime } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';

type N = { id: string; data: { title?: string; message?: string }; read_at: string | null; created_at: string };

export default function Notifications({ notifications }: PageProps<{ notifications: N[] }>) {
    const unread = notifications.filter((n) => !n.read_at).length;

    return (
        <AppLayout
            title={<>Notifi<Accent>cations</Accent></>}
            subtitle={unread > 0 ? `${unread} unread` : 'You’re all caught up.'}
            actions={
                unread > 0 && (
                    <Button variant="outline" size="sm" onClick={() => router.post('/notifications/read', {}, { preserveScroll: true })}>
                        <CheckCheck className="h-4 w-4" /> Mark all read
                    </Button>
                )
            }
        >
            <Head title="Notifications" />
            <Reveal>
                {notifications.length === 0 ? (
                    <EmptyState icon={Bell} title="No notifications yet" body="Payout updates and important account news will show up here." />
                ) : (
                    <ul className="space-y-2.5">
                        {notifications.map((n) => (
                            <li
                                key={n.id}
                                className={cn(
                                    'flex items-start gap-3 rounded-[1.25rem] border p-4 shadow-[var(--shadow-card)]',
                                    n.read_at ? 'border-tint/[0.07] bg-card' : 'border-brand-500/30 bg-brand-500/[0.06]',
                                )}
                            >
                                <span className={cn('grid h-10 w-10 shrink-0 place-items-center rounded-full', n.read_at ? 'bg-tint/[0.05] text-slate-500' : 'bg-brand-500 text-white')}>
                                    <Bell className="h-[18px] w-[18px]" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="font-semibold text-ink">{n.data.title ?? 'Notification'}</p>
                                        {!n.read_at && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500" aria-label="Unread" />}
                                    </div>
                                    {n.data.message && <p className="mt-0.5 text-sm text-slate-500">{n.data.message}</p>}
                                    <p className="mt-1.5 text-xs text-slate-400">{formatDateTime(n.created_at)}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Reveal>
        </AppLayout>
    );
}
