import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import AuthLayout from '@/Layouts/AuthLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function Login({ canResetPassword, status }: { canResetPassword: boolean; status?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/login', { onFinish: () => reset('password') });
    }

    return (
        <AuthLayout title="Welcome back" subtitle="Sign in to your TicketFlow account">
            <Head title="Login" />
            {status && <div className="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{status}</div>}

            <form onSubmit={submit} className="space-y-4">
                <Field label="Email" error={errors.email}>
                    <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="username" required autoFocus />
                </Field>
                <Field label="Password" error={errors.password}>
                    <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} autoComplete="current-password" required />
                </Field>

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm text-slate-600">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="h-4 w-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500"
                        />
                        Remember me
                    </label>
                    {canResetPassword && (
                        <Link href="/forgot-password" className="text-sm font-medium text-brand-600 hover:text-brand-300">
                            Forgot password?
                        </Link>
                    )}
                </div>

                <Button type="submit" disabled={processing} className="w-full" size="lg">
                    Sign in
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-slate-500">
                New to TicketFlow?{' '}
                <Link href="/register" className="font-semibold text-brand-600 hover:text-brand-300">
                    Create an account
                </Link>
            </p>
        </AuthLayout>
    );
}
