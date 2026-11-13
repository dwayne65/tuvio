import { Button } from '@/Components/ui/Button';
import { Accent, Avatar, Panel } from '@/Components/app/Kit';
import { Field, Input } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

type TwoFactorState = {
    enabled: boolean;
    required: boolean;
    pending: boolean;
    qrSvg: string | null;
    setupKey: string | null;
    recoveryCodes: string[] | null;
};

export default function ProfileSettings({
    mustVerifyEmail,
    status,
    twoFactor,
}: PageProps<{ mustVerifyEmail: boolean; status?: string; twoFactor: TwoFactorState }>) {
    const { auth } = usePage<PageProps>().props;
    const user = auth.user!;

    const profile = useForm({
        name: user.name,
        email: user.email,
        phone: '',
        _method: 'patch',
    });

    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const del = useForm({ password: '' });

    function saveProfile(e: FormEvent) {
        e.preventDefault();
        profile.post('/settings/profile', { preserveScroll: true });
    }
    function savePassword(e: FormEvent) {
        e.preventDefault();
        password.put('/settings/password', {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    }
    function deleteAccount(e: FormEvent) {
        e.preventDefault();
        del.delete('/settings/profile', { preserveScroll: true });
    }

    return (
        <AppLayout title={<>Account <Accent>settings</Accent></>}>
            <Head title="Settings" />
            {status && <div className="mx-auto mb-4 max-w-2xl rounded-2xl border border-emerald-500/25 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700">{status}</div>}

            <div className="mx-auto max-w-2xl space-y-5">
                <div className="flex items-center gap-4 rounded-[1.5rem] border border-tint/[0.07] bg-card p-5 shadow-[var(--shadow-card)]">
                    <Avatar name={user.name} className="h-14 w-14 text-xl" />
                    <div className="min-w-0">
                        <p className="truncate text-lg font-bold text-ink">{user.name}</p>
                        <p className="truncate text-sm text-slate-500">{user.email}</p>
                    </div>
                </div>
                {/* Profile */}
                <Panel title="Profile information" bodyClassName="pt-2">
                        {mustVerifyEmail && (
                            <p className="mb-4 rounded-2xl bg-amber-500/10 px-4 py-2.5 text-sm text-amber-700">Your email address is unverified.</p>
                        )}
                        <form onSubmit={saveProfile} className="mt-2 space-y-4">
                            <Field label="Name" error={profile.errors.name}>
                                <Input value={profile.data.name} onChange={(e) => profile.setData('name', e.target.value)} required />
                            </Field>
                            <Field label="Email" error={profile.errors.email}>
                                <Input type="email" value={profile.data.email} onChange={(e) => profile.setData('email', e.target.value)} required />
                            </Field>
                            <Field label="Phone" error={profile.errors.phone}>
                                <Input type="tel" inputMode="tel" value={profile.data.phone} onChange={(e) => profile.setData('phone', e.target.value)} placeholder="+256…" />
                            </Field>
                            <Button type="submit" disabled={profile.processing}>Save changes</Button>
                        </form>
                </Panel>

                {/* Password */}
                <Panel title="Update password" bodyClassName="pt-4">
                        <form onSubmit={savePassword} className="space-y-4">
                            <Field label="Current password" error={password.errors.current_password}>
                                <Input type="password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} autoComplete="current-password" />
                            </Field>
                            <Field label="New password" error={password.errors.password}>
                                <Input type="password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} autoComplete="new-password" />
                            </Field>
                            <Field label="Confirm new password" error={password.errors.password_confirmation}>
                                <Input type="password" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} autoComplete="new-password" />
                            </Field>
                            <Button type="submit" disabled={password.processing}>Update password</Button>
                        </form>
                </Panel>

                <TwoFactorPanel twoFactor={twoFactor} />

                {/* Danger zone */}
                <Panel title={<span className="text-danger">Delete account</span>} className="border-rose-500/30" bodyClassName="pt-2">
                        <p className="mt-1 text-sm text-slate-500">
                            Permanently delete your account and all associated data. This cannot be undone.
                        </p>
                        <form onSubmit={deleteAccount} className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                            <Field label="Confirm password" error={del.errors.password}>
                                <Input type="password" value={del.data.password} onChange={(e) => del.setData('password', e.target.value)} />
                            </Field>
                            <Button type="submit" variant="danger" disabled={del.processing}>Delete account</Button>
                        </form>
                </Panel>
            </div>
        </AppLayout>
    );
}

function TwoFactorPanel({ twoFactor }: { twoFactor: TwoFactorState }) {
    const { auth } = usePage<PageProps>().props;
    const confirm = useForm({ code: '' });
    const manage = useForm({ password: '' });

    function confirmCode(e: FormEvent) {
        e.preventDefault();
        confirm.post('/settings/two-factor/confirm', { preserveScroll: true, onFinish: () => confirm.reset() });
    }
    function send(method: 'post' | 'delete', url: string) {
        manage.submit(method, url, { preserveScroll: true, onFinish: () => manage.reset() });
    }

    const status = twoFactor.enabled ? (
        <span className="rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">On</span>
    ) : (
        <span className="rounded-full bg-slate-500/10 px-2.5 py-0.5 text-xs font-semibold text-slate-600">Off</span>
    );

    const passwordField = (
        <Field label="Confirm password" error={manage.errors.password}>
            <Input
                type="password"
                value={manage.data.password}
                onChange={(e) => manage.setData('password', e.target.value)}
                autoComplete="current-password"
            />
        </Field>
    );

    return (
        <Panel title={<span className="flex items-center gap-2">Two-factor authentication {status}</span>} bodyClassName="pt-2">
            <p className="mt-1 text-sm text-slate-500">
                Protect your account with a 6-digit code from an authenticator app (Google Authenticator, Authy, 1Password) every time you sign
                in. Even if someone learns your password, they cannot get in without your phone.
            </p>

            {auth.user?.is_admin && !twoFactor.required && !twoFactor.enabled && (
                <p className="mt-4 rounded-2xl bg-brand-500/10 px-4 py-2.5 text-sm text-ink">
                    Your admin sign-ins are already protected by a 6-digit code we email you. An authenticator app is optional extra protection.
                </p>
            )}
            {twoFactor.required && !twoFactor.enabled && (
                <p className="mt-4 rounded-2xl bg-amber-500/10 px-4 py-2.5 text-sm text-amber-700">
                    Administrator accounts must turn this on before they can open the admin area.
                </p>
            )}

            {twoFactor.recoveryCodes && (
                <div className="mt-5 rounded-2xl border border-tint/[0.07] bg-tint/[0.03] p-4">
                    <p className="text-sm font-semibold text-ink">Recovery codes</p>
                    <p className="mt-1 text-sm text-slate-500">
                        Store these somewhere safe. Each one works once if you lose your phone. They will not be shown again.
                    </p>
                    <ul className="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-ink">
                        {twoFactor.recoveryCodes.map((code) => (
                            <li key={code} className="rounded-lg bg-card px-3 py-1.5">{code}</li>
                        ))}
                    </ul>
                </div>
            )}

            {twoFactor.pending && twoFactor.qrSvg && (
                <div className="mt-5 space-y-4">
                    <div className="flex flex-col items-start gap-4 sm:flex-row">
                        <div className="rounded-2xl bg-white p-3" dangerouslySetInnerHTML={{ __html: twoFactor.qrSvg }} />
                        <div className="min-w-0 text-sm text-slate-500">
                            <p>1. Scan this QR code with your authenticator app.</p>
                            <p className="mt-2">Can't scan? Enter this key manually:</p>
                            <p className="mt-1 break-all font-mono text-ink">{twoFactor.setupKey}</p>
                            <p className="mt-2">2. Enter the 6-digit code the app shows.</p>
                        </div>
                    </div>
                    <form onSubmit={confirmCode} className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <Field label="Authentication code" error={confirm.errors.code}>
                            <Input
                                value={confirm.data.code}
                                onChange={(e) => confirm.setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                placeholder="123456"
                            />
                        </Field>
                        <Button type="submit" disabled={confirm.processing}>Confirm and turn on</Button>
                    </form>
                </div>
            )}

            {!twoFactor.pending && (
                <form onSubmit={(e) => e.preventDefault()} className="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
                    {passwordField}
                    {twoFactor.enabled ? (
                        <>
                            <Button type="button" variant="secondary" disabled={manage.processing} onClick={() => send('post', '/settings/two-factor/recovery-codes')}>
                                New recovery codes
                            </Button>
                            <Button type="button" variant="danger" disabled={manage.processing} onClick={() => send('delete', '/settings/two-factor')}>
                                Turn off
                            </Button>
                        </>
                    ) : (
                        <Button type="button" disabled={manage.processing} onClick={() => send('post', '/settings/two-factor')}>
                            Turn on two-factor
                        </Button>
                    )}
                </form>
            )}
        </Panel>
    );
}
