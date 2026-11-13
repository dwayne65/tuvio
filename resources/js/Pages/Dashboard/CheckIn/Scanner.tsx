import { Button } from '@/Components/ui/Button';
import AppLayout from '@/Layouts/AppLayout';
import {
    Manifest, QueuedScan, ScanOutcome, clearManifest, deviceId, loadGate, loadManifest, loadQueue,
    looksLikeTicketCode, markUsedLocally, saveGate, saveManifest, saveQueue, verifyOffline,
} from '@/lib/offlineCheckIn';
import { cn } from '@/lib/utils';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { Activity, CheckCircle2, CircleHelp, CloudOff, DoorOpen, Download, RefreshCw, ScanLine, Settings2, Trash2, Wifi, XCircle } from 'lucide-react';
import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';

interface Stats { sold: number; checked_in: number; remaining: number }
interface Result extends ScanOutcome { offline?: boolean }
interface Conflict { label: string; message: string }

// GREEN = valid · YELLOW = already checked in (needs a look) · RED = everything else
const palette: Record<string, { bg: string; icon: JSX.Element; label: string }> = {
    valid: { bg: 'bg-emerald-500', icon: <CheckCircle2 className="h-16 w-16" />, label: 'VALID — Entry permitted' },
    duplicate: { bg: 'bg-amber-500', icon: <CircleHelp className="h-16 w-16" />, label: 'ALREADY CHECKED IN' },
    invalid: { bg: 'bg-rose-500', icon: <XCircle className="h-16 w-16" />, label: 'INVALID TICKET' },
    wrong_event: { bg: 'bg-rose-500', icon: <XCircle className="h-16 w-16" />, label: 'WRONG EVENT' },
    unpaid: { bg: 'bg-rose-500', icon: <XCircle className="h-16 w-16" />, label: 'UNPAID' },
    void: { bg: 'bg-rose-500', icon: <XCircle className="h-16 w-16" />, label: 'VOID / REFUNDED' },
    unverified: { bg: 'bg-slate-600', icon: <CloudOff className="h-16 w-16" />, label: 'CAN’T VERIFY OFFLINE' },
};

const SYNC_BATCH = 200;

export default function Scanner({
    event,
    stats: initialStats,
    gates,
    canManage,
    canViewDashboard,
}: PageProps<{ event: { title: string; slug: string }; stats: Stats; gates: string[]; canManage: boolean; canViewDashboard: boolean }>) {
    const [stats, setStats] = useState(initialStats);
    const [result, setResult] = useState<Result | null>(null);
    const [scanId, setScanId] = useState(0);
    const [manual, setManual] = useState('');
    const [scanning, setScanning] = useState(false);
    const [online, setOnline] = useState(typeof navigator === 'undefined' ? true : navigator.onLine);
    const [manifest, setManifest] = useState<Manifest | null>(null);
    const [queue, setQueue] = useState<QueuedScan[]>([]);
    const [conflicts, setConflicts] = useState<Conflict[]>([]);
    const [syncing, setSyncing] = useState(false);
    const [preparing, setPreparing] = useState(false);
    const [gate, setGate] = useState<string>('');
    const [cameraError, setCameraError] = useState<string | null>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const lastRef = useRef<string>('');
    const manifestRef = useRef<Manifest | null>(null);

    const csrf = () => (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
    const headers = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' });

    // Restore this device's offline list, pending scans and chosen entrance.
    useEffect(() => {
        const stored = loadManifest(event.slug);
        manifestRef.current = stored;
        setManifest(stored);
        setQueue(loadQueue(event.slug));
        const savedGate = loadGate(event.slug);
        setGate(savedGate && gates.includes(savedGate) ? savedGate : gates[0] ?? '');
    }, [event.slug, gates]);

    useEffect(() => {
        const up = () => setOnline(true);
        const down = () => setOnline(false);
        window.addEventListener('online', up);
        window.addEventListener('offline', down);
        return () => {
            window.removeEventListener('online', up);
            window.removeEventListener('offline', down);
        };
    }, []);

    const prepareOffline = useCallback(async (silent = false) => {
        setPreparing(true);
        try {
            const res = await fetch(`/dashboard/check-in/${event.slug}/manifest`, { headers: headers(), credentials: 'same-origin' });
            if (!res.ok) throw new Error();
            const data: Manifest = await res.json();
            // Keep tickets this device already admitted offline marked as used.
            for (const pending of loadQueue(event.slug)) {
                if (pending.local_result === 'valid') {
                    const hit = data.tickets.find((t) => t.c === pending.label);
                    if (hit) hit.s = 'used';
                }
            }
            if (!saveManifest(event.slug, data) && !silent) {
                setConflicts((c) => [...c, { label: 'Offline list', message: 'This device has no room to store the offline list.' }]);
            }
            manifestRef.current = data;
            setManifest(data);
        } catch {
            if (!silent) setOnline(navigator.onLine);
        } finally {
            setPreparing(false);
        }
    }, [event.slug]);

    // Download the offline list on arrival so the gate keeps working if the network drops.
    useEffect(() => {
        if (navigator.onLine) prepareOffline(true);
    }, [prepareOffline]);

    const enqueue = useCallback((scan: QueuedScan) => {
        setQueue((current) => {
            const next = [...current, scan];
            saveQueue(event.slug, next);
            return next;
        });
    }, [event.slug]);

    const syncNow = useCallback(async () => {
        const pending = loadQueue(event.slug);
        if (pending.length === 0 || syncing) return;
        setSyncing(true);
        try {
            for (let i = 0; i < pending.length; i += SYNC_BATCH) {
                const batch = pending.slice(i, i + SYNC_BATCH);
                const res = await fetch(`/dashboard/check-in/${event.slug}/sync`, {
                    method: 'POST',
                    headers: headers(),
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        device_id: deviceId(),
                        scans: batch.map(({ client_uuid, qr_token, code, scanned_at, gate: g }) => ({ client_uuid, qr_token, code, scanned_at, gate: g })),
                    }),
                });
                if (!res.ok) throw new Error();
                const data: { results: (ScanOutcome & { client_uuid: string })[]; stats: Stats } = await res.json();

                const disagreements = data.results
                    .map((r) => ({ server: r, local: batch.find((b) => b.client_uuid === r.client_uuid) }))
                    .filter(({ server, local }) => local?.local_result === 'valid' && server.result !== 'valid')
                    .map(({ server, local }) => ({ label: server.ticket?.attendee_name ?? local!.label, message: server.message }));
                if (disagreements.length) setConflicts((c) => [...disagreements, ...c].slice(0, 20));

                const synced = new Set(data.results.map((r) => r.client_uuid));
                const remaining = loadQueue(event.slug).filter((q) => !synced.has(q.client_uuid));
                saveQueue(event.slug, remaining);
                setQueue(remaining);
                setStats({ sold: data.stats.sold, checked_in: data.stats.checked_in, remaining: data.stats.remaining });
            }
        } catch {
            /* still offline — keep the queue and retry later */
        } finally {
            setSyncing(false);
        }
    }, [event.slug, syncing]);

    // Sync as soon as the connection is back, then keep trying while scans are pending.
    useEffect(() => {
        if (!online || queue.length === 0) return;
        syncNow();
        const timer = window.setInterval(syncNow, 20000);
        return () => window.clearInterval(timer);
    }, [online, queue.length, syncNow]);

    const show = useCallback((outcome: Result) => {
        setResult(outcome);
        setScanId((n) => n + 1);
        if (navigator.vibrate) navigator.vibrate(outcome.result === 'valid' ? 80 : [60, 40, 60]);
    }, []);

    const scanOffline = useCallback(async (input: { qr_token?: string; code?: string }, label: string) => {
        const list = manifestRef.current;
        if (!list) {
            show({ result: 'unverified', message: 'No connection and no offline list on this device. Reconnect, or download the offline list before doors open.', ticket: null, offline: true });
            return;
        }
        const outcome = await verifyOffline(event.slug, list, input);
        setManifest({ ...list });
        enqueue({ client_uuid: crypto.randomUUID(), ...input, scanned_at: new Date().toISOString(), gate: gate || null, local_result: outcome.result, label: outcome.ticket?.code ?? label });
        show({ ...outcome, offline: true });
    }, [enqueue, event.slug, gate, show]);

    const submit = useCallback(async (raw: string) => {
        const value = raw.trim();
        if (!value || value === lastRef.current) return;
        lastRef.current = value;
        setTimeout(() => (lastRef.current = ''), 2500); // allow re-scan after a pause

        const input = looksLikeTicketCode(value) ? { code: value } : { qr_token: value };
        const label = input.code ?? 'Scanned ticket';

        if (!navigator.onLine) {
            setOnline(false);
            await scanOffline(input, label);
            return;
        }

        try {
            const res = await fetch(`/dashboard/check-in/${event.slug}/scan`, {
                method: 'POST',
                headers: headers(),
                credentials: 'same-origin',
                body: JSON.stringify({ ...input, client_uuid: crypto.randomUUID(), gate: gate || null, device_id: deviceId() }),
            });
            if (!res.ok) throw new Error(String(res.status));
            const data = await res.json();
            if (data.result === 'valid') markUsedLocally(event.slug, manifestRef.current, data.ticket?.code);
            show(data);
            if (data.stats) setStats(data.stats);
        } catch (error) {
            // Network failure mid-scan: fall back to the offline list instead of leaving the gate stuck.
            if (error instanceof TypeError) {
                setOnline(false);
                await scanOffline(input, label);
            }
        }
    }, [event.slug, gate, scanOffline, show]);

    // Camera scanning: the native BarcodeDetector where available, otherwise jsQR
    // (loaded on demand) for iPhone Safari and Firefox.
    useEffect(() => {
        if (!scanning) return;
        let stream: MediaStream | null = null;
        let raf = 0;
        let stopped = false;

        (async () => {
            try {
                const Detector = (window as any).BarcodeDetector;
                let read: (video: HTMLVideoElement) => Promise<string | null>;
                if (Detector) {
                    const detector = new Detector({ formats: ['qr_code'] });
                    read = async (video) => {
                        const codes = await detector.detect(video);
                        return codes.length ? codes[0].rawValue : null;
                    };
                } else {
                    const jsQR = (await import('jsqr')).default;
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d', { willReadFrequently: true })!;
                    read = async (video) => {
                        // Downscale large frames; QR codes on a phone screen read fine at 640px.
                        const scale = Math.min(1, 640 / Math.max(video.videoWidth, video.videoHeight));
                        canvas.width = Math.round(video.videoWidth * scale);
                        canvas.height = Math.round(video.videoHeight * scale);
                        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                        const frame = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        return jsQR(frame.data, frame.width, frame.height, { inversionAttempts: 'dontInvert' })?.data || null;
                    };
                }

                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                if (stopped) {
                    stream.getTracks().forEach((t) => t.stop());
                    return;
                }
                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                    await videoRef.current.play();
                }
                const tick = async () => {
                    if (stopped) return;
                    if (videoRef.current && videoRef.current.readyState === 4) {
                        try {
                            const value = await read(videoRef.current);
                            if (value) await submit(value);
                        } catch { /* frame skip */ }
                    }
                    raf = requestAnimationFrame(tick);
                };
                raf = requestAnimationFrame(tick);
            } catch {
                setScanning(false);
                setCameraError('Camera access was blocked or no camera was found. Allow camera access, or type the ticket code below.');
            }
        })();

        return () => {
            stopped = true;
            cancelAnimationFrame(raf);
            stream?.getTracks().forEach((t) => t.stop());
        };
    }, [scanning, submit]);

    const p = result ? palette[result.result] ?? palette.invalid : null;
    const cameraSupported = typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;
    const pct = stats.sold > 0 ? Math.round((stats.checked_in / stats.sold) * 100) : 0;

    return (
        <AppLayout
            title={event.title}
            back={{ href: '/dashboard/check-in', label: 'All events' }}
            actions={
                (canViewDashboard || canManage) && (
                    <div className="flex gap-2">
                        {canViewDashboard && (
                            <Button href={`/dashboard/check-in/${event.slug}/live`} variant="outline" size="sm"><Activity className="h-4 w-4" /> Live</Button>
                        )}
                        {canManage && (
                            <Button href={`/dashboard/check-in/${event.slug}/setup`} variant="outline" size="sm"><Settings2 className="h-4 w-4" /> Setup</Button>
                        )}
                    </div>
                )
            }
        >
            <Head title={`Check-in · ${event.title}`} />

            {/* Connection + offline readiness */}
            <div
                className={cn(
                    'mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-[1.25rem] border px-4 py-3 text-sm',
                    online ? 'border-tint/[0.07] bg-card' : 'border-amber-400/40 bg-amber-500/10',
                )}
                role="status"
            >
                <span className={cn('inline-flex items-center gap-2 font-semibold', online ? 'text-emerald-700' : 'text-amber-700')}>
                    {online ? <Wifi className="h-4 w-4" /> : <CloudOff className="h-4 w-4" />}
                    {online ? 'Online' : 'Offline — scans are checked against this device’s list'}
                </span>
                <span className="text-slate-500">
                    {manifest
                        ? `Offline list: ${manifest.tickets.length.toLocaleString()} tickets · ${new Date(manifest.generated_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
                        : 'Offline list not downloaded'}
                </span>
                {queue.length > 0 && (
                    <span className="inline-flex items-center gap-1.5 font-semibold text-amber-700">
                        <RefreshCw className={cn('h-3.5 w-3.5', syncing && 'animate-spin')} /> {queue.length} waiting to sync
                    </span>
                )}
                <span className="ml-auto flex gap-1.5">
                    {online && queue.length > 0 && (
                        <Button size="sm" variant="outline" onClick={syncNow} disabled={syncing}>Sync now</Button>
                    )}
                    {online && (
                        <Button size="sm" variant="ghost" onClick={() => prepareOffline()} disabled={preparing}>
                            <Download className="h-4 w-4" /> {manifest ? 'Refresh list' : 'Prepare offline'}
                        </Button>
                    )}
                    {manifest && queue.length === 0 && (
                        <button
                            type="button"
                            onClick={() => { clearManifest(event.slug); manifestRef.current = null; setManifest(null); }}
                            aria-label="Remove offline list from this device"
                            className="grid h-9 w-9 place-items-center rounded-full text-slate-400 hover:bg-tint/[0.06] hover:text-ink"
                        >
                            <Trash2 className="h-4 w-4" />
                        </button>
                    )}
                </span>
            </div>

            {!online && (
                <p className="mb-4 rounded-2xl bg-tint/[0.04] px-4 py-3 text-xs leading-relaxed text-slate-500">
                    <strong className="text-ink">Offline limits:</strong> keep this page open until you reconnect. Tickets bought or refunded after the list was downloaded, and tickets scanned
                    on other devices, can’t be seen until sync — the server’s record wins and any disagreement is listed below.
                </p>
            )}

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
                    {gates.length > 0 && (
                        <label className="mb-3 flex items-center gap-2 rounded-2xl border border-tint/10 bg-tint/[0.03] px-3 py-2">
                            <DoorOpen className="h-4 w-4 shrink-0 text-brand-300" />
                            <span className="text-sm font-semibold text-slate-500">Entrance</span>
                            <select
                                value={gate}
                                onChange={(e) => { setGate(e.target.value); saveGate(event.slug, e.target.value); }}
                                className="h-9 min-w-0 flex-1 rounded-xl bg-transparent text-sm font-bold text-ink focus:outline-none"
                            >
                                {gates.map((g) => <option key={g} value={g}>{g}</option>)}
                            </select>
                        </label>
                    )}

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
                                        className="absolute inset-x-2 h-0.5 rounded-full bg-brand-400 shadow-[0_0_16px_4px_rgb(124_70_255/0.6)]"
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
                        <Button className="mt-4 w-full" size="lg" variant={scanning ? 'outline' : 'primary'} onClick={() => { setCameraError(null); setScanning((s) => !s); }}>
                            <ScanLine className="h-4 w-4" /> {scanning ? 'Stop camera' : 'Start scanning'}
                        </Button>
                    ) : (
                        <p className="mt-4 rounded-2xl bg-amber-500/10 px-4 py-3 text-center text-sm text-amber-700">
                            This browser can’t open the camera here (it needs HTTPS). Type the ticket code (e.g. TKT-…) below.
                        </p>
                    )}
                    {cameraError && <p className="mt-3 rounded-2xl bg-rose-500/10 px-4 py-3 text-center text-sm text-rose-700 dark:text-rose-300">{cameraError}</p>}

                    <form
                        onSubmit={(e: FormEvent) => { e.preventDefault(); submit(manual); setManual(''); }}
                        className="mt-3 flex items-center gap-2 rounded-full border border-tint/10 bg-tint/[0.03] p-1.5"
                    >
                        <input
                            value={manual}
                            onChange={(e) => setManual(e.target.value)}
                            placeholder="Or type the ticket code (TKT-…)"
                            aria-label="Ticket code"
                            autoCapitalize="characters"
                            autoCorrect="off"
                            className="h-10 min-w-0 flex-1 bg-transparent pl-3 text-sm text-ink placeholder:text-slate-400 focus:outline-none"
                        />
                        <Button type="submit" size="sm" disabled={!manual.trim()}>Check</Button>
                    </form>
                </section>

                {/* Result */}
                <div aria-live="assertive" className="space-y-4">
                    <AnimatePresence mode="wait">
                        {p ? (
                            <motion.div
                                key={scanId}
                                initial={{ opacity: 0, scale: 0.92, y: 10 }}
                                animate={{ opacity: 1, scale: 1, y: 0 }}
                                exit={{ opacity: 0, scale: 0.96 }}
                                transition={{ type: 'spring', stiffness: 320, damping: 22 }}
                                className={`relative flex min-h-[18rem] flex-col items-center justify-center rounded-[1.5rem] ${p.bg} p-8 text-center text-white shadow-premium`}
                            >
                                {result!.offline && (
                                    <span className="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-black/20 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide">
                                        <CloudOff className="h-3.5 w-3.5" /> Offline
                                    </span>
                                )}
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
                                <div className="flex min-h-[18rem] flex-col items-center justify-center rounded-[1.5rem] border border-dashed border-tint/[0.12] bg-card/60 p-8 text-center">
                                    <span className="grid h-14 w-14 place-items-center rounded-full bg-brand-500/10 text-brand-300">
                                        <ScanLine className="h-6 w-6" />
                                    </span>
                                    <p className="mt-4 font-semibold text-ink">Ready to scan</p>
                                    <p className="mt-1 text-sm text-slate-500">Results appear here — green to let in, amber already used, red to refuse.</p>
                                </div>
                            </motion.div>
                        )}
                    </AnimatePresence>

                    {conflicts.length > 0 && (
                        <div className="rounded-[1.25rem] border border-amber-400/40 bg-amber-500/10 p-4">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm font-bold text-amber-800 dark:text-amber-700">Needs attention after sync</p>
                                <button type="button" onClick={() => setConflicts([])} className="text-xs font-semibold text-amber-700 hover:underline">Dismiss</button>
                            </div>
                            <p className="mt-0.5 text-xs text-amber-700">Admitted offline, but the server disagreed (e.g. scanned at another entrance first).</p>
                            <ul className="mt-2 space-y-1 text-sm">
                                {conflicts.map((c, i) => (
                                    <li key={i} className="flex justify-between gap-3 text-ink"><span className="truncate font-semibold">{c.label}</span><span className="shrink-0 text-slate-500">{c.message}</span></li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>

            {canManage && gates.length === 0 && (
                <p className="mt-5 text-center text-sm text-slate-500">
                    Running more than one entrance?{' '}
                    <Link href={`/dashboard/check-in/${event.slug}/setup`} className="font-semibold text-brand-600 hover:underline">Add entrances and gate staff</Link>
                </p>
            )}
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
