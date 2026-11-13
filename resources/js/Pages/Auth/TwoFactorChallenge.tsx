import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import AuthLayout from '@/Layouts/AuthLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ code: '', recovery_code: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/two-factor-challenge', { onFinish: () => reset() });
    }

    function toggle() {
        setUseRecovery(!useRecovery);
        reset();
        clearErrors();
    }

    return (
        <AuthLayout
            title="Two-step verification"
            subtitle={useRecovery ? 'Enter one of your saved recovery codes' : 'Enter the 6-digit code from your authenticator app'}
        >
            <Head title="Two-step verification" />

            <form onSubmit={submit} className="space-y-4">
                {useRecovery ? (
                    <Field label="Recovery code" error={errors.recovery_code}>
                        <Input
                            value={data.recovery_code}
                            onChange={(e) => setData('recovery_code', e.target.value)}
                            autoComplete="off"
                            placeholder="abcde-12345"
                            required
                            autoFocus
                        />
                    </Field>
                ) : (
                    <Field label="Authentication code" error={errors.code}>
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
                )}

                <Button type="submit" disabled={processing} className="w-full" size="lg">
                    Verify and sign in
                </Button>
            </form>

            <div className="mt-6 flex items-center justify-between text-sm">
                <button type="button" onClick={toggle} className="font-medium text-brand-600 hover:text-brand-300">
                    {useRecovery ? 'Use authenticator code' : 'Lost your phone? Use a recovery code'}
                </button>
                <Link href="/login" className="text-slate-500 hover:text-slate-700">
                    Back to sign in
                </Link>
            </div>
        </AuthLayout>
    );
}
