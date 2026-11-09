import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import AuthLayout from '@/Layouts/AuthLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/forgot-password');
    }

    return (
        <AuthLayout title="Reset your password" subtitle="We'll email you a secure reset link">
            <Head title="Forgot password" />
            {status && <div className="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">{status}</div>}

            <form onSubmit={submit} className="space-y-4">
                <Field label="Email" error={errors.email}>
                    <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required autoFocus />
                </Field>
                <Button type="submit" disabled={processing} className="w-full" size="lg">
                    Email reset link
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-slate-500">
                <Link href="/login" className="font-semibold text-brand-600 hover:text-brand-300">
                    Back to sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
