import { Button } from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { CheckCircle2, CircleHelp, ScanLine, XCircle } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

interface Stats { sold: number; checked_in: number; remaining: number }
interface Result { result: string; message: string; ticket: { code: string; type: string; attendee_name: string | null } | null }

// GREEN = valid · YELLOW = already checked in (needs a look) · RED = everything else
const palette: Record<string, { bg: string; icon: JSX.Element; label: string }> = {
    valid: { bg: 'bg-emerald-500', icon: <CheckCircle2 className="h-16 w-16" />, label: 'VALID — Entry permitted' },
    duplicate: { bg: 'bg-amber-500', icon: <CircleHelp className="h-16 w-16" />, label: 'ALREADY CHECKED IN' },
    invalid: { bg: 'bg-rose-500', icon: <XCircle className="h-16 w-16" />, label: 'INVALID TICKET' },
    wrong_event: { bg: 'bg-rose-500', icon: <XCircle className="h-16 w-16" />, label: 'WRONG EVENT' },
    unpaid: { bg: 'bg-rose-500', icon: <XCircle className="h-16 w-16" />, label: 'UNPAID' },
    void: { bg: 'bg-rose-500', icon: <XCircle className="h-16 w-16" />, label: 'VOID / REFUNDED' },
};

export default function Scanner({ event, stats: initialStats }: PageProps<{ event: { title: string; slug: string }; stats: Stats }>) {
    const [stats, setStats] = useState(initialStats);
    const [result, setResult] = useState<Result | null>(null);
    const [scanId, setScanId] = useState(0);
    const [manual, setManual] = useState('');
    const [scanning, setScanning] = useState(false);
    const videoRef = useRef<HTMLVideoElement>(null);
    const lastRef = useRef<string>('');

    const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

    const submit = useCallback(async (token: string) => {
        if (!token || token === lastRef.current) return;
        lastRef.current = token;
        setTimeout(() => (lastRef.current = ''), 2500); // allow re-scan after a pause

        const res = await fetch(`/dashboard/check-in/${event.slug}/scan`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ qr_token: token, client_uuid: crypto.randomUUID() }),
        });
        if (!res.ok) return;
        const data = await res.json();
        setResult(data);
        setScanId((n) => n + 1);
        if (data.stats) setStats(data.stats);
        if (navigator.vibrate) navigator.vibrate(data.result === 'valid' ? 80 : [60, 40, 60]);
    }, [event.slug]);

    // Camera scanning via the native BarcodeDetector where available.
    useEffect(() => {
        if (!scanning) return;
        let stream: MediaStream | null = null;
        let raf = 0;
        const Detector = (window as any).BarcodeDetector;
        if (!Detector) {
            setScanning(false);
            return;
        }
        const detector = new Detector({ formats: ['qr_code'] });

        (async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    await videoRef.current.play();
                }
                const tick = async () => {
                    if (videoRef.current && videoRef.current.readyState === 4) {
                        try {
                            const codes = await detector.detect(videoRef.current);
                            if (codes.length) await submit(codes[0].rawValue);
                        } catch { /* frame skip */ }
                    }
                    raf = requestAnimationFrame(tick);
                };
                raf = requestAnimationFrame(tick);
            } catch {
                setScanning(false);
            }
        })();

        return () => {
            cancelAnimationFrame(raf);
            stream?.getTracks().forEach((t) => t.stop());
        };
    }, [scanning, submit]);

    const p = result ? palette[result.result] ?? palette.invalid : null;
    const cameraSupported = typeof window !== 'undefined' && 'BarcodeDetector' in window;
    const pct = stats.sold > 0 ? Math.round((stats.checked_in / stats.sold) * 100) : 0;

    return (
        <AppLayout title={event.title} back={{ href: '/dashboard/check-in', label: 'All events' }}>
            <Head title={`Check-in · ${event.title}`} />

            {/* Live attendance */}
            <div className="mb-5 rounded-[1.5rem] border border-tint/[0.07] bg-card p-4 shadow-[var(--shadow-card)] sm:p-5">
                <div className="grid grid-cols-3 divide-x divide-tint/[0.07] text-center">
                    <StatTile label="Sold" value={stats.sold} />
                    <StatTile label="Checked in" value={stats.checked_in} accent />
                    <StatTile label="Remaining" value={stats.remaining} />
                </div>
                <div className="mt-4 h-2 overflow-hidden rounded-full bg-tint/[0.06]" role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100} aria-label="Checked in">
                    <motion.div className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500" animate={{ width: `${pct}%` }} />
                </div>
            </div>

            <div className="grid gap-5 lg:grid-cols-2">
                <section className="rounded-[1.5rem] border border-tint/[0.07] bg-card p-4 shadow-[var(--shadow-card)] sm:p-5">
                    {/* Viewfinder */}
                    <div className="relative overflow-hidden rounded-[1.25rem] bg-[#07080c]">
                        <video ref={videoRef} className="aspect-square w-full object-cover" muted playsInline />
                        <div className="pointer-events-none absolute inset-0 grid place-items-center">
                            <div className="relative h-[62%] w-[62%]">
                                {['left-0 top-0 border-l-4 border-t-4 rounded-tl-2xl', 'right-0 top-0 border-r-4 border-t-4 rounded-tr-2xl', 'bottom-0 left-0 border-b-4 border-l-4 rounded-bl-2xl', 'bottom-0 right-0 border-b-4 border-r-4 rounded-br-2xl'].map((c) => (
                                    <span key={c} className={`absolute h-10 w-10 border-white/90 ${c}`} />
                                ))}
                                {scanning && (
                                    <motion.span
                                        className="absolute inset-x-2 h-0.5 rounded-full bg-brand-400 shadow-[0_0_16px_4px_rgb(47_125_255/0.6)]"
                                        animate={{ top: ['8%', '92%', '8%'] }}
                                        transition={{ duration: 2.4, repeat: Infinity, ease: 'easeInOut' }}
                                    />
                                )}
                            </div>
                        </div>
                        {!scanning && (
                            <div className="absolute inset-0 grid place-items-center bg-black/40 text-center text-white">
                                <div>
                                    <ScanLine className="mx-auto h-10 w-10 opacity-80" />
                                    <p className="mt-2 text-sm font-medium text-white/80">{cameraSupported ? 'Camera is off' : 'Camera scanning unavailable'}</p>
                                </div>
                            </div>
                        )}
                    </div>

                    {cameraSupported ? (
                        <Button className="mt-4 w-full" size="lg" variant={scanning ? 'outline' : 'primary'} onClick={() => setScanning((s) => !s)}>
                            <ScanLine className="h-4 w-4" /> {scanning ? 'Stop camera' : 'Start scanning'}
                        </Button>
                    ) : (
                        <p className="mt-4 rounded-2xl bg-amber-500/10 px-4 py-3 text-center text-sm text-amber-700">
                            This browser can’t read QR codes from the camera. Use Chrome on Android, or type the code below.
                        </p>
                    )}

                    <form
                        onSubmit={(e: FormEvent) => { e.preventDefault(); submit(manual.trim()); setManual(''); }}
                        className="mt-3 flex items-center gap-2 rounded-full border border-tint/10 bg-tint/[0.03] p-1.5"
                    >
                        <input
                            value={manual}
                            onChange={(e) => setManual(e.target.value)}
                            placeholder="Or enter QR token manually"
                            aria-label="Ticket QR token"
                            autoCapitalize="off"
                            autoCorrect="off"
                            className="h-10 min-w-0 flex-1 bg-transparent pl-3 text-sm text-ink placeholder:text-slate-400 focus:outline-none"
                        />
                        <Button type="submit" size="sm" disabled={!manual.trim()}>Check</Button>
                    </form>
                </section>

                {/* Result */}
                <div aria-live="assertive">
                    <AnimatePresence mode="wait">
                        {p ? (
                            <motion.div
                                key={scanId}
                                initial={{ opacity: 0, scale: 0.92, y: 10 }}
                                animate={{ opacity: 1, scale: 1, y: 0 }}
                                exit={{ opacity: 0, scale: 0.96 }}
                                transition={{ type: 'spring', stiffness: 320, damping: 22 }}
                                className={`flex h-full min-h-[18rem] flex-col items-center justify-center rounded-[1.5rem] ${p.bg} p-8 text-center text-white shadow-premium`}
                            >
                                {p.icon}
                                <h3 className="mt-4 text-2xl font-extrabold tracking-tight">{p.label}</h3>
                                <p className="mt-1 text-white/90">{result!.message}</p>
                                {result!.ticket && (
                                    <div className="mt-5 w-full max-w-xs rounded-2xl bg-black/15 px-4 py-3">
                                        <p className="text-lg font-bold">{result!.ticket.attendee_name ?? 'Guest'}</p>
                                        <p className="text-sm text-white/80">{result!.ticket.type} · {result!.ticket.code}</p>
                                    </div>
                                )}
                            </motion.div>
                        ) : (
                            <motion.div key="idle" initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}>
                                <div className="flex h-full min-h-[18rem] flex-col items-center justify-center rounded-[1.5rem] border border-dashed border-tint/[0.12] bg-card/60 p-8 text-center">
                                    <span className="grid h-14 w-14 place-items-center rounded-full bg-brand-500/10 text-brand-300">
                                        <ScanLine className="h-6 w-6" />
                                    </span>
                                    <p className="mt-4 font-semibold text-ink">Ready to scan</p>
                                    <p className="mt-1 text-sm text-slate-500">Results appear here — green to let in, amber already used, red to refuse.</p>
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>
                </div>
            </div>
        </AppLayout>
    );
}

function StatTile({ label, value, accent }: { label: string; value: number; accent?: boolean }) {
    return (
        <div className="px-2">
            <p className={`text-2xl font-extrabold tracking-tight sm:text-3xl ${accent ? 'text-emerald-600' : 'text-ink'}`}>{value.toLocaleString()}</p>
            <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">{label}</p>
        </div>
    );
}
