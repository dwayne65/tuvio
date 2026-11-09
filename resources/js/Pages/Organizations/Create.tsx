import { Button } from '@/Components/ui/Button';
import { Accent } from '@/Components/app/Kit';
import { Field, Input, Select } from '@/Components/ui/Input';
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { BarChart3, QrCode, Wallet } from 'lucide-react';
import { FormEvent } from 'react';

export default function OrganizationCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        country: 'UG',
        default_currency: 'UGX',
        support_email: '',
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        post('/organizations');
    }

    return (
        <AppLayout title={<>Become an <Accent>organizer</Accent></>} subtitle="Set up your profile — team members and payout accounts can come later.">
            <Head title="Become an organizer" />
            <div className="mx-auto max-w-xl">
                <div className="mb-5 grid grid-cols-3 gap-2.5">
                    {[
                        { icon: QrCode, label: 'QR tickets' },
                        { icon: Wallet, label: 'Fast payouts' },
                        { icon: BarChart3, label: 'Live insights' },
                    ].map((f) => (
                        <div key={f.label} className="flex flex-col items-center gap-2 rounded-[1.25rem] border border-tint/[0.07] bg-card p-3 text-center shadow-[var(--shadow-card)]">
                            <span className="grid h-10 w-10 place-items-center rounded-full bg-brand-500/10 text-brand-300"><f.icon className="h-[18px] w-[18px]" /></span>
                            <span className="text-xs font-semibold text-ink">{f.label}</span>
                        </div>
                    ))}
                </div>
                <section className="rounded-[1.5rem] border border-tint/[0.07] bg-card p-5 shadow-[var(--shadow-card)] sm:p-7">
                        <form onSubmit={submit} className="space-y-4">
                            <Field label="Organization name" error={errors.name}>
                                <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required autoFocus placeholder="e.g. Kampala Events Co." />
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label="Country" error={errors.country}>
                                    <Select value={data.country} onChange={(e) => setData('country', e.target.value)}>
                                        <option value="UG">Uganda</option>
                                        <option value="KE">Kenya</option>
                                        <option value="TZ">Tanzania</option>
                                        <option value="NG">Nigeria</option>
                                        <option value="GH">Ghana</option>
                                        <option value="ZA">South Africa</option>
                                        <option value="US">United States</option>
                                        <option value="GB">United Kingdom</option>
                                    </Select>
                                </Field>
                                <Field label="Default currency" error={errors.default_currency}>
                                    <Select value={data.default_currency} onChange={(e) => setData('default_currency', e.target.value)}>
                                        <option value="UGX">UGX</option>
                                        <option value="KES">KES</option>
                                        <option value="TZS">TZS</option>
                                        <option value="NGN">NGN</option>
                                        <option value="GHS">GHS</option>
                                        <option value="ZAR">ZAR</option>
                                        <option value="USD">USD</option>
                                        <option value="GBP">GBP</option>
                                    </Select>
                                </Field>
                            </div>
                            <Field label="Support email (optional)" error={errors.support_email}>
                                <Input type="email" value={data.support_email} onChange={(e) => setData('support_email', e.target.value)} placeholder="support@yourorg.com" />
                            </Field>
                            <Button type="submit" disabled={processing} className="w-full" size="lg">
                                Create organizer profile
                            </Button>
                        </form>
                </section>
            </div>
        </AppLayout>
    );
}
