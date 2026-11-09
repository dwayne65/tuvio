import { Button } from '@/Components/ui/Button';
import { Field, Input } from '@/Components/ui/Input';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, useForm } from '@inertiajs/react';
import { Mail, MapPin, Phone } from 'lucide-react';
import { FormEvent } from 'react';

export default function Contact() {
    const { data, setData, processing, reset } = useForm({ name: '', email: '', message: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        // Wired to a support inbox in a later phase; validate client-side for now.
        reset();
        alert('Thanks! Our team will get back to you shortly.');
    }

    return (
        <PublicLayout>
            <Head title="Contact" />
            <section className="container-page grid gap-10 py-16 lg:grid-cols-2">
                <div>
                    <h1 className="text-4xl font-extrabold tracking-tight text-ink">Get in touch</h1>
                    <p className="mt-3 text-slate-500">
                        Questions about events, payments or your account? We're here to help.
                    </p>
                    <div className="mt-8 space-y-4">
                        <ContactRow icon={<Mail className="h-5 w-5" />} label="Email" value="support@ticketflow.test" />
                        <ContactRow icon={<Phone className="h-5 w-5" />} label="Phone" value="+256 700 000 000" />
                        <ContactRow icon={<MapPin className="h-5 w-5" />} label="Office" value="Kampala, Uganda" />
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-4 rounded-2xl border border-slate-200 bg-card p-6">
                    <Field label="Name">
                        <Input value={data.name} onChange={(e) => setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Email">
                        <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} required />
                    </Field>
                    <Field label="Message">
                        <textarea
                            value={data.message}
                            onChange={(e) => setData('message', e.target.value)}
                            required
                            rows={5}
                            className="w-full rounded-xl border border-slate-300 bg-card px-3.5 py-2.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/40"
                        />
                    </Field>
                    <Button type="submit" disabled={processing} className="w-full">
                        Send message
                    </Button>
                </form>
            </section>
        </PublicLayout>
    );
}

function ContactRow({ icon, label, value }: { icon: React.ReactNode; label: string; value: string }) {
    return (
        <div className="flex items-center gap-3">
            <span className="grid h-10 w-10 place-items-center rounded-xl bg-brand-50 text-brand-600">{icon}</span>
            <div>
                <p className="text-xs font-medium uppercase tracking-wide text-slate-400">{label}</p>
                <p className="font-medium text-ink">{value}</p>
            </div>
        </div>
    );
}
