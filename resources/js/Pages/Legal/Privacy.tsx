import { LegalDocument, LegalSection } from '@/Components/LegalDocument';
import { Link } from '@inertiajs/react';
import { Ban, Eye, KeyRound, Lock, ShieldCheck, UserCheck } from 'lucide-react';

const sections: LegalSection[] = [
    {
        id: 'overview',
        title: 'Overview',
        body: (
            <>
                <p>
                    This Privacy Policy explains what personal data TUVIO collects, why, who we share it with and the choices you have. It applies
                    to everyone who uses TUVIO: attendees (with or without an account), organizers, their staff and promoters.
                </p>
                <p>
                    We process personal data in line with the Data Protection and Privacy Act, 2019 of Uganda and other laws that apply where you
                    use TUVIO.
                </p>
            </>
        ),
    },
    {
        id: 'collect',
        title: 'What we collect',
        body: (
            <>
                <ul>
                    <li><strong>Checkout details:</strong> your name, email and, if you give it, phone number, so we can deliver your tickets and contact you about your order.</li>
                    <li><strong>Account details:</strong> name, email, password (stored only as a secure hash) and optional profile photo.</li>
                    <li><strong>Orders and tickets:</strong> what you bought, when, the amount paid, promo codes used and whether your ticket was scanned at the gate.</li>
                    <li><strong>Payment information:</strong> handled by our payment partner. We receive confirmation and a reference, <strong>never your full card number or Mobile Money PIN</strong>.</li>
                    <li><strong>Things you send us:</strong> support requests, reviews and ratings.</li>
                    <li><strong>Technical data:</strong> IP address, device and browser type, and basic usage logs that keep the service secure.</li>
                </ul>
            </>
        ),
    },
    {
        id: 'use',
        title: 'How we use it',
        body: (
            <ul>
                <li>To process orders, verify payments, issue and email tickets, and check you in at events.</li>
                <li>To run accounts, payouts and reports for organizers.</li>
                <li>To credit promoters for sales that came through their referral links.</li>
                <li>To answer support requests and send important service messages.</li>
                <li>To prevent fraud, counterfeit tickets and abuse, and to keep accurate financial records.</li>
                <li>To improve TUVIO, using aggregated statistics wherever possible.</li>
            </ul>
        ),
    },
    {
        id: 'legal-basis',
        title: 'Why we’re allowed to',
        body: (
            <p>
                We use your data because it is needed to provide what you asked for (selling you a ticket or running your organizer account), to
                meet legal obligations such as keeping financial records, for our legitimate interest in keeping the Platform safe, or with your
                consent, which you can withdraw at any time, for example for our newsletter.
            </p>
        ),
    },
    {
        id: 'sharing',
        title: 'Who we share it with',
        body: (
            <>
                <p>We never sell your personal data. We share it only when needed:</p>
                <ul>
                    <li><strong>The event organizer</strong> receives your name, email and ticket details so they can admit you and contact you about the event.</li>
                    <li><strong>Payment partners</strong> process your payment securely.</li>
                    <li><strong>Service providers</strong> such as hosting and email delivery, under contracts that protect your data.</li>
                    <li><strong>Authorities</strong> when the law requires it, or to protect people from harm or fraud.</li>
                </ul>
                <p>Reviews are public and show the name you entered at checkout.</p>
            </>
        ),
    },
    {
        id: 'cookies',
        title: 'Cookies',
        body: (
            <p>
                TUVIO uses only essential cookies: a session cookie that keeps you signed in and remembers your checkout (including which
                promoter link brought you), and a security token that protects forms. We don’t use third-party advertising cookies. The day and
                night theme is worked out from your device’s clock and isn’t stored.
            </p>
        ),
    },
    {
        id: 'security',
        title: 'How we protect it',
        body: (
            <ul>
                <li>All traffic is encrypted with HTTPS.</li>
                <li>Passwords are hashed, never stored in plain text.</li>
                <li>Tickets use random secure tokens, and download links are cryptographically signed.</li>
                <li>Check-in staff can only scan tickets for the events they’re assigned to.</li>
            </ul>
        ),
    },
    {
        id: 'retention',
        title: 'How long we keep it',
        body: (
            <p>
                We keep order and payment records for as long as tax and accounting law requires. Account data is kept while your account is
                open. When you delete your account we remove your account and profile data; order and payment records we must keep by law are
                retained for the required period and then deleted.
            </p>
        ),
    },
    {
        id: 'rights',
        title: 'Your rights',
        body: (
            <>
                <p>You can ask us to:</p>
                <ul>
                    <li>give you a copy of your personal data;</li>
                    <li>correct anything that’s wrong;</li>
                    <li>delete your data, where we don’t need to keep it by law;</li>
                    <li>stop or limit certain uses, including marketing emails.</li>
                </ul>
                <p>
                    You can update your profile or delete your account from Settings, or send any request through the{' '}
                    <Link href="/contact">Contact page</Link>. You may also complain to the Personal Data Protection Office in Uganda.
                </p>
            </>
        ),
    },
    {
        id: 'children',
        title: 'Children',
        body: <p>TUVIO accounts are for people aged 18 or over. Younger fans may attend events where the organizer allows it, with tickets bought by a parent or guardian.</p>,
    },
    {
        id: 'changes',
        title: 'Changes to this policy',
        body: (
            <p>
                If we change how we use your data, we will update this page and its “last updated” date. For significant changes we will notify
                account holders by email or in the app. See also our <Link href="/terms">Terms of Service</Link>.
            </p>
        ),
    },
];

export default function Privacy() {
    return (
        <LegalDocument
            icon={ShieldCheck}
            title="Privacy"
            accent="Policy."
            intro="Your data helps get you through the door, and that’s all. Here is exactly what we collect, why, and how you stay in control."
            updated="9 November 2026"
            summary={[
                { icon: Ban, text: 'We never sell your personal data.' },
                { icon: Lock, text: 'Card and Mobile Money details stay with our payment partner. We never see your PIN.' },
                { icon: UserCheck, text: 'Organizers get only what they need to admit you to their event.' },
                { icon: Eye, text: 'Only essential cookies. No advertising trackers.' },
                { icon: KeyRound, text: 'Ask for a copy of your data, a correction or deletion at any time.' },
                { icon: ShieldCheck, text: 'Encrypted connections, hashed passwords and signed ticket links.' },
            ]}
            sections={sections}
        />
    );
}
