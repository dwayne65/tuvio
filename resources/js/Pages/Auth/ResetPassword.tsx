import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import AuthLayout from '@/Layouts/AuthLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function ResetPassword({ token, email }: { token: string; email: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/reset-password', { onFinish: () => reset('password', 'password_confirmation') });
    }

    return (
        <AuthLayout title="Choose a new password">
            <Head title="Reset password" />
            <form onSubmit={submit} className="space-y-4">
                <Field label="Email" error={errors.email}>
                    <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                </Field>
                <Field label="New password" error={errors.password}>
                    <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} autoComplete="new-password" required autoFocus />
                </Field>
                <Field label="Confirm password" error={errors.password_confirmation}>
                    <Input type="password" value={data.password_confirmation} onChange={(e) => setData('password_confirmation', e.target.value)} autoComplete="new-password" required />
                </Field>
                <Button type="submit" disabled={processing} className="w-full" size="lg">
                    Reset password
                </Button>
            </form>
        </AuthLayout>
    );
}
