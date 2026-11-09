import { Button } from '@/Components/ui/Button';
import AuthLayout from '@/Layouts/AuthLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/email/verification-notification');
    }

    return (
        <AuthLayout title="Verify your email" subtitle="We've sent a verification link to your inbox.">
            <Head title="Verify email" />
            {status === 'verification-link-sent' && (
                <div className="mb-4 rounded-lg bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                    A fresh verification link has been sent to your email address.
                </div>
            )}
            <p className="text-sm text-slate-500">
                Before continuing, please verify your email by clicking the link we just sent. Didn't
                get it? Request another below.
            </p>
            <form onSubmit={submit} className="mt-6 flex items-center justify-between">
                <Button type="submit" disabled={processing}>
                    Resend verification email
                </Button>
                <Link href="/logout" method="post" as="button" className="text-sm font-medium text-slate-500 hover:text-ink">
                    Log out
                </Link>
            </form>
        </AuthLayout>
    );
}
