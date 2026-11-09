import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import AuthLayout from '@/Layouts/AuthLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/register', { onFinish: () => reset('password', 'password_confirmation') });
    }

    return (
        <AuthLayout title="Create your account" subtitle="Start discovering events or selling tickets in minutes">
            <Head title="Register" />
            <form onSubmit={submit} className="space-y-4">
                <Field label="Full name" error={errors.name}>
                    <Input value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="name" required autoFocus />
                </Field>
                <Field label="Email" error={errors.email}>
                    <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} autoComplete="username" required />
                </Field>
                <Field label="Password" error={errors.password}>
                    <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} autoComplete="new-password" required />
                </Field>
                <Field label="Confirm password" error={errors.password_confirmation}>
                    <Input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} autoComplete="new-password" required />
                </Field>

                <Button type="submit" disabled={processing} className="w-full" size="lg">
                    Create account
                </Button>
            </form>

            <p className="mt-6 text-center text-sm text-slate-500">
                Already have an account?{' '}
                <Link href="/login" className="font-semibold text-brand-600 hover:text-brand-300">
                    Sign in
                </Link>
            </p>
        </AuthLayout>
    );
}
