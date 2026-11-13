import { Button } from '@/Components/ui/Button';
import { Field, Input, Textarea } from '@/Components/ui/Input';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Clock, LifeBuoy, Mail, MapPin, Phone, Send } from 'lucide-react';
import { FormEvent, ReactNode } from 'react';

const topics = ['I didn’t receive my tickets', 'Change or refund a ticket', 'Payment problem', 'Question about an event', 'Something else'];

export default function Contact({ prefill }: PageProps<{ prefill: { name: string; email: string; order_reference: string } }>) {
    const form = useForm({ ...prefill, subject: prefill.order_reference ? topics[0] : '', message: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/support', {
            preserveScroll: true,
            onSuccess: () => form.reset('subject', 'message'),
        });
    }

    return (
        <PublicLayout>
            <Head title="Contact & support" />
            <section className="container-page grid gap-8 py-8 md:py-16 lg:grid-cols-[1fr_1.2fr] lg:gap-14">
                <div>
                    <p className="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-bold uppercase tracking-[0.14em] text-brand-300">
                        <LifeBuoy className="h-3.5 w-3.5" /> Support
                    </p>
                    <h1 className="mt-4 text-[2.2rem] font-extrabold leading-[1.05] tracking-[-0.045em] text-ink md:text-5xl">
                        How can we <span className="font-display font-normal italic tracking-normal text-gradient">help?</span>
                    </h1>
                    <p className="mt-3 max-w-md text-slate-500">
                        Add your order reference and your message goes straight to the event organizer. Everything else reaches the TUVIO team.
                    </p>
                    <div className="mt-8 space-y-3">
                        <ContactRow icon={<Mail className="h-5 w-5" />} label="Email" value="support@tuvio.app" />
                        <ContactRow icon={<Phone className="h-5 w-5" />} label="Phone" value="+256 700 000 000" />
                        <ContactRow icon={<MapPin className="h-5 w-5" />} label="Office" value="Kampala, Uganda" />
                        <ContactRow icon={<Clock className="h-5 w-5" />} label="Replies" value="Usually within a few hours" />
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-4 rounded-[1.75rem] border border-tint/[0.08] bg-card/80 p-5 shadow-premium sm:p-7">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Name" error={form.errors.name}>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} autoComplete="name" required />
                        </Field>
                        <Field label="Email" error={form.errors.email}>
                            <Input type="email" inputMode="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} autoComplete="email" required />
                        </Field>
                    </div>
                    <Field label="Order reference (optional)" error={form.errors.order_reference}>
                        <Input
                            value={form.data.order_reference}
                            onChange={(e) => form.setData('order_reference', e.target.value.toUpperCase())}
                            placeholder="TF-AB12CD34"
                            className="font-mono uppercase"
                        />
                    </Field>
                    <div className="space-y-1.5">
                        <span className="text-sm font-medium text-ink">What’s it about?</span>
                        <div className="flex flex-wrap gap-2">
                            {topics.map((t) => (
                                <button
                                    key={t}
                                    type="button"
                                    onClick={() => form.setData('subject', t)}
                                    aria-pressed={form.data.subject === t}
                                    className={
                                        form.data.subject === t
                                            ? 'rounded-full border border-brand-500 bg-brand-500 px-3.5 py-1.5 text-xs font-semibold text-white'
                                            : 'rounded-full border border-tint/[0.1] bg-card px-3.5 py-1.5 text-xs font-semibold text-slate-600 hover:text-ink'
                                    }
                                >
                                    {t}
                                </button>
                            ))}
                        </div>
                        {form.errors.subject && <span className="block text-sm text-danger">Choose a topic.</span>}
                    </div>
                    <Field label="Message" error={form.errors.message}>
                        <Textarea value={form.data.message} onChange={(e) => form.setData('message', e.target.value)} rows={5} required placeholder="Tell us what happened…" />
                    </Field>
                    <Button type="submit" size="lg" disabled={form.processing || !form.data.subject} className="w-full">
                        <Send className="h-4 w-4" /> Send message
                    </Button>
                </form>
            </section>
        </PublicLayout>
    );
}

function ContactRow({ icon, label, value }: { icon: ReactNode; label: string; value: string }) {
    return (
        <div className="flex items-center gap-3 rounded-2xl border border-tint/[0.06] bg-card/60 p-3">
            <span className="grid h-10 w-10 place-items-center rounded-full bg-brand-500/10 text-brand-300">{icon}</span>
            <div>
                <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">{label}</p>
                <p className="font-semibold text-ink">{value}</p>
            </div>
        </div>
    );
}
