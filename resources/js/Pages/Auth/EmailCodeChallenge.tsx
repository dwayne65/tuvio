import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import AuthLayout from '@/Layouts/AuthLayout';
import { PageProps } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { MailCheck, ShieldCheck } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

type Props = { email: string; ttlMinutes: number; resendSeconds: number; hasAuthenticator: boolean };

export default function EmailCodeChallenge({ email, ttlMinutes, resendSeconds, hasAuthenticator }: Props) {
    const { flash } = usePage<PageProps>().props;
    const { data, setData, post, processing, errors, reset } = useForm({ code: '' });
    const [cooldown, setCooldown] = useState(resendSeconds);
    const [resending, setResending] = useState(false);

    useEffect(() => {
        if (cooldown <= 0) return;
        const timer = setTimeout(() => setCooldown((s) => s - 1), 1000);
        return () => clearTimeout(timer);
    }, [cooldown]);

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/login/email-code', { onFinish: () => reset() });
    }

    function resend() {
        setResending(true);
        router.post('/login/email-code/resend', {}, {
            preserveScroll: true,
            onFinish: () => {
                setResending(false);
                setCooldown(resendSeconds);
            },
        });
    }

    return (
        <AuthLayout title="Check your email" subtitle={`We sent a 6-digit sign-in code to ${email}`}>
            <Head title="Email verification" />

            <div className="mb-5 flex items-start gap-3 rounded-2xl bg-brand-500/10 p-4 text-sm text-slate-600">
                <MailCheck className="mt-0.5 h-5 w-5 shrink-0 text-brand-500" />
                <p>
                    For your security, every sign-in to this account needs a code from your inbox. It expires in {ttlMinutes} minutes.
                    {hasAuthenticator && ' Next, you will also confirm with your authenticator app.'}
                </p>
            </div>

            {flash.success && <div className="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{flash.success}</div>}
            {flash.error && <div className="mb-4 rounded-lg bg-rose-50 px-4 py-2 text-sm text-rose-700">{flash.error}</div>}

            <form onSubmit={submit} className="space-y-4">
                <Field label="Sign-in code" error={errors.code}>
                    <Input
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        placeholder="123456"
                        className="text-center text-lg tracking-[0.5em]"
                        required
                        autoFocus
                    />
                </Field>

                <Button type="submit" disabled={processing || data.code.length !== 6} className="w-full" size="lg">
                    <ShieldCheck className="h-4 w-4" /> {hasAuthenticator ? 'Verify and continue' : 'Verify and sign in'}
                </Button>
            </form>

            <div className="mt-6 flex items-center justify-between text-sm">
                <button
                    type="button"
                    onClick={resend}
                    disabled={cooldown > 0 || resending}
                    className="font-medium text-brand-600 hover:text-brand-300 disabled:cursor-not-allowed disabled:text-slate-400"
                >
                    {cooldown > 0 ? `Resend code in ${cooldown}s` : 'Resend code'}
                </button>
                <Link href="/login" className="text-slate-500 hover:text-slate-700">
                    Back to sign in
                </Link>
            </div>
        </AuthLayout>
    );
}
