import { LegalDocument, LegalSection } from '@/Components/LegalDocument';
import { Link } from '@inertiajs/react';
import { BadgeCheck, FileText, QrCode, RotateCcw, Scale, Wallet } from 'lucide-react';

const sections: LegalSection[] = [
    {
        id: 'agreement',
        title: 'Agreement to these terms',
        body: (
            <>
                <p>
                    These Terms of Service (“Terms”) govern your use of TUVIO, including our website, apps, ticketing, check-in and payout tools
                    (together, the “Platform”). By browsing, buying a ticket, creating an account or listing an event, you agree to these Terms
                    and to our <Link href="/privacy">Privacy Policy</Link>.
                </p>
                <p>If you use TUVIO on behalf of a company or organization, you confirm you are authorised to accept these Terms for it.</p>
            </>
        ),
    },
    {
        id: 'roles',
        title: 'Who does what',
        body: (
            <>
                <p>TUVIO is a marketplace that connects three groups:</p>
                <ul>
                    <li><strong>Attendees</strong> who discover events and buy tickets.</li>
                    <li><strong>Organizers</strong> who create events, set prices and policies, and run them.</li>
                    <li><strong>TUVIO</strong>, which provides the technology, processes payments through licensed partners and pays organizers.</li>
                </ul>
                <p>
                    The organizer, not TUVIO, is responsible for the event itself: the line-up, venue, safety, age limits, start times and any
                    changes. When you buy a ticket, your agreement to attend is with the organizer.
                </p>
            </>
        ),
    },
    {
        id: 'accounts',
        title: 'Accounts',
        body: (
            <>
                <p>
                    You can buy tickets without an account. To sell tickets, manage events or keep a ticket wallet you need an account. You must
                    be at least 18, or have a parent or guardian’s permission, and give accurate information.
                </p>
                <p>
                    Keep your password private. You are responsible for activity on your account. Tell us straight away through the{' '}
                    <Link href="/contact">Contact page</Link> if you think it has been accessed without permission.
                </p>
            </>
        ),
    },
    {
        id: 'tickets',
        title: 'Buying tickets',
        body: (
            <>
                <ul>
                    <li>Prices are set by the organizer and shown before you pay, including any discount from a valid promo code.</li>
                    <li>Tickets are held for a short time during checkout. If payment isn’t completed, they are released to other buyers.</li>
                    <li>An order is confirmed only after we verify the payment with our payment provider. Your tickets are then emailed to the address you give us.</li>
                    <li>Organizers may limit how many tickets one person can buy.</li>
                </ul>
            </>
        ),
    },
    {
        id: 'qr',
        title: 'Using your ticket',
        body: (
            <>
                <p>
                    Each ticket has a unique QR code that can be scanned <strong>once</strong>. Anyone holding the code can use it, so keep it safe
                    and don’t post it online. We are not responsible for tickets that are shared, copied or screenshotted by someone else before
                    you arrive.
                </p>
                <p>
                    Organizers may refuse entry for reasons such as a ticket that was already scanned, safety concerns, age limits or venue rules.
                    Reselling tickets above face value or using automated tools to buy them is not allowed and may lead to cancellation.
                </p>
            </>
        ),
    },
    {
        id: 'refunds',
        title: 'Refunds, changes and cancellations',
        body: (
            <>
                <p>
                    Each event shows the organizer’s refund policy. Unless that policy says otherwise, tickets are non-refundable once purchased.
                </p>
                <ul>
                    <li><strong>Cancelled event:</strong> you are entitled to a refund of the ticket price.</li>
                    <li><strong>Postponed or materially changed event:</strong> your ticket stays valid for the new date; the organizer’s policy sets whether you can request a refund instead.</li>
                    <li>Refunds go back to the original payment method. Timing depends on your bank or Mobile Money provider.</li>
                </ul>
                <p>To ask for a refund, use the <Link href="/contact">Contact page</Link> with your order reference.</p>
            </>
        ),
    },
    {
        id: 'organizers',
        title: 'Rules for organizers',
        body: (
            <>
                <p>If you list an event, you agree that:</p>
                <ul>
                    <li>Your event information is accurate, and you have every licence and permission needed to run it.</li>
                    <li>You will honour every valid ticket and your published refund policy.</li>
                    <li>You are responsible for promo codes and promoter commissions you create, and for any taxes on your sales.</li>
                    <li>You will use attendee data only to run the event and in line with our <Link href="/privacy">Privacy Policy</Link>.</li>
                </ul>
            </>
        ),
    },
    {
        id: 'fees',
        title: 'Fees and payouts',
        body: (
            <>
                <p>
                    TUVIO charges organizers the commission and subscription fees shown on our <Link href="/pricing">Pricing page</Link> or agreed
                    in writing. Fees are deducted from ticket revenue before payout.
                </p>
                <p>
                    Payouts are made to the payout account the organizer provides, once funds have cleared. We may hold payouts if we suspect fraud,
                    receive chargebacks, or the event is cancelled and refunds are due.
                </p>
            </>
        ),
    },
    {
        id: 'content',
        title: 'Reviews and your content',
        body: (
            <>
                <p>
                    You keep ownership of what you post, such as event descriptions, images and reviews. You give TUVIO a licence to display and
                    promote it on the Platform.
                </p>
                <p>
                    Only verified ticket buyers can review an event. Reviews must be honest and must not be abusive, hateful or misleading.
                    Organizers and TUVIO may hide reviews that break these rules.
                </p>
            </>
        ),
    },
    {
        id: 'acceptable-use',
        title: 'Acceptable use',
        body: (
            <ul>
                <li>No fraud, fake events or counterfeit tickets.</li>
                <li>No attempts to break, overload or reverse-engineer the Platform.</li>
                <li>No scraping or automated buying.</li>
                <li>No illegal content or events, and nothing that infringes other people’s rights.</li>
            </ul>
        ),
    },
    {
        id: 'liability',
        title: 'Liability',
        body: (
            <>
                <p>
                    We work hard to keep TUVIO available and secure, but the Platform is provided “as is”. To the extent the law allows, TUVIO is
                    not liable for the conduct of organizers or attendees, or for anything that happens at an event, and our total liability to
                    you for any claim is limited to the fees you paid us for the order in question.
                </p>
                <p>Nothing in these Terms limits rights you have under consumer protection laws that cannot be excluded.</p>
            </>
        ),
    },
    {
        id: 'termination',
        title: 'Suspension and termination',
        body: (
            <p>
                You can stop using TUVIO at any time and delete your account from Settings. We may suspend or close accounts or remove events that
                break these Terms, after notice where reasonable. Valid tickets already sold will be handled under the refund rules above.
            </p>
        ),
    },
    {
        id: 'law',
        title: 'Changes and governing law',
        body: (
            <>
                <p>
                    We may update these Terms from time to time. We will change the “last updated” date and, for significant changes, tell
                    account holders by email or in the app. Continuing to use TUVIO after a change means you accept it.
                </p>
                <p>
                    These Terms are governed by the laws of the Republic of Uganda. We will always try to resolve a dispute informally first. Please{' '}
                    <Link href="/contact">contact us</Link> before taking any formal step.
                </p>
            </>
        ),
    },
];

export default function Terms() {
    return (
        <LegalDocument
            icon={FileText}
            title="Terms of"
            accent="Service."
            intro="The rules of the road for buying tickets, hosting events and getting paid on TUVIO, written to be read."
            updated="9 November 2026"
            summary={[
                { icon: Scale, text: 'Organizers run their events. TUVIO provides the platform, secure payments and payouts.' },
                { icon: BadgeCheck, text: 'Orders are confirmed only after your payment is verified, then tickets are emailed instantly.' },
                { icon: QrCode, text: 'Each QR ticket is valid for one scan. Keep it private, like cash.' },
                { icon: RotateCcw, text: 'Refunds follow the organizer’s policy. Cancelled events are refunded.' },
                { icon: Wallet, text: 'Organizers pay the fees shown on Pricing, deducted before payout.' },
                { icon: FileText, text: 'Be honest: no fake events, counterfeit tickets, bots or abusive reviews.' },
            ]}
            sections={sections}
        />
    );
}
