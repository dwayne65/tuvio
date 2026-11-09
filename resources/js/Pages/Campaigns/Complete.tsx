import { Button } from '@/Components/ui/Button';
import { Card, CardBody } from '@/Components/ui/Card';
import PublicLayout from '@/Layouts/PublicLayout';
import { money } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { CheckCircle2, XCircle } from 'lucide-react';

interface Contribution {
    reference: string; status: string; amount_minor: number; currency: string;
    receipt_number: string | null; campaign: { title: string; slug: string };
}

export default function Complete({ contribution }: PageProps<{ contribution: Contribution }>) {
    const ok = contribution.status === 'successful';
    return (
        <PublicLayout>
            <Head title="Contribution" />
            <div className="container-page max-w-xl py-16">
                <Card>
                    <CardBody className="text-center">
                        {ok ? <CheckCircle2 className="mx-auto h-16 w-16 text-emerald-500" /> : <XCircle className="mx-auto h-16 w-16 text-red-500" />}
                        <h1 className="mt-5 text-2xl font-extrabold text-ink">{ok ? 'Thank you!' : 'Payment not completed'}</h1>
                        <p className="mt-2 text-slate-500">
                            {ok ? `Your ${money(contribution.amount_minor, contribution.currency)} contribution to ${contribution.campaign.title} was received.` : 'Your contribution could not be verified.'}
                        </p>
                        {ok && contribution.receipt_number && <p className="mt-1 text-sm text-slate-400">Receipt {contribution.receipt_number}</p>}
                        <Button href={`/campaigns/${contribution.campaign.slug}`} variant="outline" className="mt-6">Back to campaign</Button>
                    </CardBody>
                </Card>
            </div>
        </PublicLayout>
    );
}
