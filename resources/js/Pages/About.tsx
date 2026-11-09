import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';

export default function About() {
    return (
        <PublicLayout>
            <Head title="About" />
            <section className="container-page max-w-3xl py-16">
                <h1 className="text-4xl font-extrabold tracking-tight text-ink">About TicketFlow</h1>
                <p className="mt-6 text-lg leading-relaxed text-slate-600">
                    TicketFlow is a premium event ticketing and management platform built for Africa and
                    the world. We connect organizers, attendees and contributors on one trusted platform —
                    making it effortless to create events, sell tickets, accept Mobile Money and card
                    payments, and welcome guests with secure QR check-in.
                </p>
                <p className="mt-4 leading-relaxed text-slate-600">
                    Every payment is verified server-side, every ticket carries a secure, non-guessable
                    QR token, and every shilling is tracked through an auditable double-entry ledger. That
                    means organizers get paid accurately and on time, and attendees can trust the ticket
                    in their pocket.
                </p>
                <div className="mt-10 grid gap-6 sm:grid-cols-3">
                    {[
                        ['One platform', 'Discovery, sales, payments, check-in and payouts in a single place.'],
                        ['Built for trust', 'Server-side verification, immutable ledger, secure tickets.'],
                        ['Local & global', 'Mobile Money, Visa and Mastercard across markets.'],
                    ].map(([t, d]) => (
                        <div key={t} className="rounded-2xl border border-slate-200 bg-card p-6">
                            <h3 className="font-bold text-ink">{t}</h3>
                            <p className="mt-2 text-sm text-slate-500">{d}</p>
                        </div>
                    ))}
                </div>
            </section>
        </PublicLayout>
    );
}
