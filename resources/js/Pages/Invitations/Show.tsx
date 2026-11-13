import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import AuthLayout from '@/Layouts/AuthLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Building2, CalendarClock, Lock, ShieldCheck } from 'lucide-react';
import { FormEvent } from 'react';

interface Invitation {
    token: string;
    email: string;
    organization: string;
    role: string;
    role_description: string;
    expires_at: string | null;
}

type Status = 'ready' | 'expired' | 'wrong_account' | 'register' | 'login';

export default function InvitationShow({ auth, invitation, status }: PageProps<{ invitation: Invitation; status: Status }>) {
    const subtitle =
        status === 'register'
            ? 'Create your account to accept the invitation and get started.'
            : `You've been invited to join as ${invitation.role}.`;

    return (
        <AuthLayout title={`Join ${invitation.organization}`} subtitle={subtitle}>
            <Head title={`Join ${invitation.organization}`} />

            <InviteSummary invitation={invitation} />

            <div className="mt-6">
                {status === 'ready' && <AcceptForm token={invitation.token} />}
                {status === 'register' && <RegisterForm invitation={invitation} />}

                {status === 'login' && (
                    <div className="space-y-4">
                        <p className="text-sm text-slate-500">
                            <span className="font-semibold text-ink">{invitation.email}</span> already has a TUVIO account. Sign in
                            and you'll come straight back here to accept.
                        </p>
                        <Button href={`/login?email=${encodeURIComponent(invitation.email)}`} className="w-full" size="lg">
                            Sign in to accept
                        </Button>
                    </div>
                )}

                {status === 'expired' && (
                    <p className="text-sm text-slate-500">
                        This invitation has expired. Ask the person who invited you to send a new one from their Team page.
                    </p>
                )}

                {status === 'wrong_account' && (
                    <div className="space-y-4 text-sm text-slate-500">
                        <p>
                            This invitation was sent to <span className="font-semibold text-ink">{invitation.email}</span>, but you're
                            signed in as {auth.user?.email}. Switch to the invited account to accept it.
                        </p>
                        <Link href="/logout" method="post" as="button" className="font-semibold text-brand-600 hover:text-brand-300">
                            Sign out and switch account
                        </Link>
                    </div>
                )}
            </div>
        </AuthLayout>
    );
}

function InviteSummary({ invitation }: { invitation: Invitation }) {
    return (
        <div className="rounded-2xl border border-brand-500/15 bg-brand-500/[0.06] p-4">
            <div className="flex items-center gap-3">
                <span className="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-500 text-white">
                    <Building2 className="h-5 w-5" />
                </span>
                <div className="min-w-0">
                    <p className="truncate font-bold text-ink">{invitation.organization}</p>
                    <span className="mt-0.5 inline-flex items-center gap-1 rounded-full bg-brand-500/15 px-2.5 py-0.5 text-xs font-semibold text-brand-600 dark:text-brand-300">
                        <ShieldCheck className="h-3.5 w-3.5" />
                        {invitation.role}
                    </span>
                </div>
            </div>
            <p className="mt-3 text-sm leading-relaxed text-slate-500">{invitation.role_description}</p>
            {invitation.expires_at && (
                <p className="mt-3 flex items-center gap-1.5 text-xs text-slate-400">
                    <CalendarClock className="h-3.5 w-3.5" />
                    Invitation valid until {invitation.expires_at}
                </p>
            )}
        </div>
    );
}

function AcceptForm({ token }: { token: string }) {
    const { post, processing } = useForm({});

    const accept = (e: FormEvent) => {
        e.preventDefault();
        post(`/invitations/${token}`);
    };

    return (
        <form onSubmit={accept}>
            <Button type="submit" className="w-full" size="lg" disabled={processing}>
                {processing ? 'Joining…' : 'Accept invitation'}
            </Button>
        </form>
    );
}

function RegisterForm({ invitation }: { invitation: Invitation }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        password: '',
        password_confirmation: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post(`/invitations/${invitation.token}/register`, { onFinish: () => reset('password', 'password_confirmation') });
    }

    return (
        <form onSubmit={submit} className="space-y-4">
            <Field label="Email">
                <div className="relative">
                    <Input type="email" value={invitation.email} readOnly className="pr-10 text-slate-500" autoComplete="username" />
                    <Lock className="absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </div>
            </Field>
            <Field label="Full name" error={errors.name}>
                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} autoComplete="name" required autoFocus />
            </Field>
            <Field label="Password" error={errors.password}>
                <Input type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} autoComplete="new-password" required />
            </Field>
            <Field label="Confirm password" error={errors.password_confirmation}>
                <Input
                    type="password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                    autoComplete="new-password"
                    required
                />
            </Field>

            <Button type="submit" disabled={processing} className="w-full" size="lg">
                {processing ? 'Setting up your account…' : `Create account & join as ${invitation.role}`}
            </Button>
            <p className="text-center text-xs text-slate-400">Your email is confirmed by this invitation link.</p>
        </form>
    );
}
