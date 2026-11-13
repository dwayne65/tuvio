import { Logo } from '@/Components/Logo';
import { Link } from '@inertiajs/react';
import { QrCode, ShieldCheck, Wallet } from 'lucide-react';
import { ReactNode } from 'react';

export default function AuthLayout({
    title,
    subtitle,
    children,
}: {
    title: string;
    subtitle?: string;
    children: ReactNode;
}) {
    return (
        <div className="relative flex min-h-screen overflow-hidden">
            <div aria-hidden className="pointer-events-none absolute inset-0 lg:hidden">
                <div className="aurora absolute -right-24 -top-24 h-80 w-80 rounded-full bg-brand-500/25 blur-3xl" />
            </div>

            {/* Form side */}
            <div className="relative flex w-full flex-col px-5 pb-10 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6 lg:w-1/2 lg:justify-center lg:px-20 lg:py-12">
                <div className="mx-auto flex w-full max-w-sm flex-1 flex-col lg:flex-none">
                    <Link href="/" aria-label="TUVIO home" className="inline-block">
                        <Logo variant="tagline" className="h-12 sm:h-14" />
                    </Link>
                    <div className="mt-auto pt-16 lg:mt-12 lg:pt-0">
                        <h1 className="text-[2.1rem] font-extrabold leading-[1.05] tracking-[-0.045em] text-ink">{title}</h1>
                        {subtitle && <p className="mt-2 text-[15px] text-slate-500">{subtitle}</p>}
                        <div className="mt-8 rounded-[1.75rem] border border-tint/[0.08] bg-card/70 p-5 backdrop-blur-xl sm:p-6">{children}</div>
                    </div>
                    <div className="mt-auto lg:hidden" />
                </div>
            </div>

            {/* Brand side */}
            <div className="relative m-3 hidden w-1/2 overflow-hidden rounded-[2.5rem] border border-tint/[0.08] bg-gradient-to-br from-brand-500 via-brand-800 to-[#0a1230] lg:block">
                <div className="absolute inset-0 dot-grid opacity-40" />
                <div className="aurora absolute -right-20 -top-24 h-96 w-96 rounded-full bg-white/15 blur-3xl" />
                <div className="relative flex h-full flex-col justify-end p-14 text-white">
                    <h2 className="text-5xl font-extrabold leading-[1.02] tracking-[-0.045em]">
                        Every event.
                        <br />
                        Every ticket.
                        <br />
                        <span className="font-display font-normal italic">One platform.</span>
                    </h2>
                    <ul className="mt-10 space-y-3">
                        <Feature icon={<Wallet className="h-5 w-5" />} text="Fast payouts & transparent commissions" />
                        <Feature icon={<ShieldCheck className="h-5 w-5" />} text="Server-verified payments, immutable ledger" />
                        <Feature icon={<QrCode className="h-5 w-5" />} text="Secure QR tickets, emailed instantly" />
                    </ul>
                </div>
            </div>
        </div>
    );
}

function Feature({ icon, text }: { icon: ReactNode; text: string }) {
    return (
        <li className="flex items-center gap-3 rounded-full border border-white/15 bg-white/10 p-1.5 pr-5 backdrop-blur-md">
            <span className="grid h-10 w-10 place-items-center rounded-full bg-white/15">{icon}</span>
            <span className="text-sm font-medium text-white/90">{text}</span>
        </li>
    );
}
