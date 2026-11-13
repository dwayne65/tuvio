import { Button } from '@/Components/ui/Button';
import { Card, CardBody } from '@/Components/ui/Card';
import { Field, Input } from '@/Components/ui/Input';
import PublicLayout from '@/Layouts/PublicLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import { FormEvent } from 'react';

interface Campaign {
    title: string; slug: string; description: string | null; cover_image_path: string | null;
    goal_minor: number | null; raised_minor: number; currency: string; contributors_count: number;
    progress: number; allow_anonymous: boolean; organization: { name: string }; suggested: number[];
}

export default function CampaignShow({ campaign }: PageProps<{ campaign: Campaign }>) {
    const { data, setData, post, processing, errors } = useForm({ amount_major: '', name: '', email: '', message: '', is_anonymous: false });

    function contribute(e: FormEvent) {
        e.preventDefault();
        post(`/campaigns/${campaign.slug}/contribute`);
    }

    return (
        <PublicLayout>
            <Head title={campaign.title} />
            <div className="container-page max-w-5xl py-12">
                <div className="grid gap-8 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <div className="aspect-[16/9] overflow-hidden rounded-2xl bg-gradient-to-br from-brand-400 to-brand-700">
                            {campaign.cover_image_path && <img src={campaign.cover_image_path} alt="" className="h-full w-full object-cover" />}
                        </div>
                        <p className="mt-4 text-sm text-slate-500">by {campaign.organization.name}</p>
                        <h1 className="mt-1 text-3xl font-extrabold tracking-tight text-ink">{campaign.title}</h1>
                        {campaign.description && <p className="mt-4 whitespace-pre-line text-slate-600">{campaign.description}</p>}
                    </div>

                    <div>
                        <Card className="sticky top-20">
                            <CardBody>
                                <p className="text-3xl font-extrabold text-ink">{money(campaign.raised_minor, campaign.currency)}</p>
                                {campaign.goal_minor && <p className="text-sm text-slate-500">raised of {money(campaign.goal_minor, campaign.currency)} goal</p>}
                                <div className="mt-3 h-2 overflow-hidden rounded-full bg-slate-100">
                                    <div className="h-full rounded-full bg-brand-500" style={{ width: `${campaign.progress}%` }} />
                                </div>
                                <p className="mt-2 text-sm text-slate-400">{campaign.contributors_count} contributors</p>

                                <form onSubmit={contribute} className="mt-6 space-y-3">
                                    <div className="flex flex-wrap gap-2">
                                        {campaign.suggested.map((amt) => (
                                            <button type="button" key={amt} onClick={() => setData('amount_major', String(amt / 100))}
                                                className="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:border-brand-400">
                                                {money(amt, campaign.currency)}
                                            </button>
                                        ))}
                                    </div>
                                    <Field label={`Amount (${campaign.currency})`} error={errors.amount_major}>
                                        <Input type="number" min="1" value={data.amount_major} onChange={(e) => setData('amount_major', e.target.value)} required />
                                    </Field>
                                    {!data.is_anonymous && (
                                        <>
                                            <Field label="Name"><Input value={data.name} onChange={(e) => setData('name', e.target.value)} /></Field>
                                            <Field label="Email"><Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} /></Field>
                                        </>
                                    )}
                                    {campaign.allow_anonymous && (
                                        <label className="flex items-center gap-2 text-sm text-slate-600">
                                            <input type="checkbox" checked={data.is_anonymous} onChange={(e) => setData('is_anonymous', e.target.checked)} className="h-4 w-4 rounded border-slate-300 text-brand-500" />
                                            Contribute anonymously
                                        </label>
                                    )}
                                    <Button type="submit" className="w-full" size="lg" disabled={processing}>
                                        <Heart className="h-4 w-4" /> Contribute
                                    </Button>
                                </form>
                            </CardBody>
                        </Card>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
