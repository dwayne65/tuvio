import { Button } from '@/Components/ui/Button';
import { Input } from '@/Components/ui/Input';
import { router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

/**
 * A destructive admin action guarded by the admin's own password. Opens an inline
 * panel instead of deleting straight away, so a stray click can't remove anything.
 */
export function RemoveWithPassword({
    url,
    label,
    question,
    disabled = false,
}: {
    url: string;
    label: string;
    question: string;
    disabled?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [password, setPassword] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    if (disabled) {
        return null;
    }

    if (!open) {
        return (
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="rounded-full border border-rose-500/30 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-500/10"
            >
                {label}
            </button>
        );
    }

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        setError(null);
        router.delete(url, {
            data: { password },
            preserveScroll: true,
            onError: (errors) => setError(errors.password ?? Object.values(errors)[0] ?? 'Could not complete this action.'),
            onSuccess: () => {
                setOpen(false);
                setPassword('');
            },
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <form onSubmit={submit} className="flex w-full min-w-[15rem] flex-col gap-2 rounded-2xl border border-rose-500/20 bg-rose-500/5 p-3">
            <p className="text-xs font-semibold text-ink">{question}</p>
            <Input
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Your admin password"
                aria-label="Your admin password"
                autoComplete="current-password"
                required
            />
            {error && <p className="text-xs text-rose-600">{error}</p>}
            <div className="flex gap-2">
                <Button type="submit" variant="danger" size="sm" disabled={processing || password === ''}>
                    {processing ? 'Removing…' : 'Confirm'}
                </Button>
                <Button type="button" variant="ghost" size="sm" onClick={() => { setOpen(false); setError(null); setPassword(''); }}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}
