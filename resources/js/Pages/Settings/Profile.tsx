import { Button } from '@/Components/ui/Button';
import { Accent, Avatar, Panel } from '@/Components/app/Kit';
import { Field, Input } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function ProfileSettings({ mustVerifyEmail, status }: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
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
