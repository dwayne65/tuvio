/*
 | Offline gate scanning. The device keeps a manifest of SHA-256 hashes of each
 | ticket's QR token (the server never sends the tokens) plus a queue of scans
 | made while offline. Queued scans are replayed on the server when the
 | connection returns; the server's answer is authoritative.
 |
 | Storage is per device and per event. Every access is wrapped because
 | storage can be unavailable (private mode, blocked site data).
*/

export interface ManifestTicket {
    /** sha256(qr_token), hex */
    h: string;
    /** printed ticket code, e.g. TKT-AB12CD34EF */
    c: string;
    n: string | null;
    t: string | null;
    s: 'valid' | 'used' | 'void' | 'unpaid';
    u: string | null;
}

export interface Manifest {
    event: string;
    generated_at: string;
    tickets: ManifestTicket[];
}

export interface QueuedScan {
    client_uuid: string;
    qr_token?: string;
    code?: string;
    scanned_at: string;
    gate?: string | null;
    /** What the device told the gate staff, to spot disagreements after sync. */
    local_result: string;
    label: string;
}

export interface ScanOutcome {
    result: string;
    message: string;
    ticket: { code: string; type: string | null; attendee_name: string | null } | null;
}

const key = (slug: string, part: string) => `tf:checkin:${slug}:${part}`;

function read<T>(storageKey: string): T | null {
    try {
        const raw = window.localStorage.getItem(storageKey);
        return raw ? (JSON.parse(raw) as T) : null;
    } catch {
        return null;
    }
}

function write(storageKey: string, value: unknown): boolean {
    try {
        window.localStorage.setItem(storageKey, JSON.stringify(value));
        return true;
    } catch {
        return false;
    }
}

export function deviceId(): string {
    const existing = read<string>('tf:device-id');
    if (existing) {
        return existing;
    }
    const id = crypto.randomUUID();
    write('tf:device-id', id);
    return id;
}

export const loadManifest = (slug: string) => read<Manifest>(key(slug, 'manifest'));
export const saveManifest = (slug: string, manifest: Manifest) => write(key(slug, 'manifest'), manifest);
export const loadQueue = (slug: string) => read<QueuedScan[]>(key(slug, 'queue')) ?? [];
export const saveQueue = (slug: string, queue: QueuedScan[]) => write(key(slug, 'queue'), queue);
export const loadGate = (slug: string) => read<string>(key(slug, 'gate'));
export const saveGate = (slug: string, gate: string) => write(key(slug, 'gate'), gate);

/** Removes the manifest (attendee names) from this device. Pending scans are kept until synced. */
export function clearManifest(slug: string): void {
    try {
        window.localStorage.removeItem(key(slug, 'manifest'));
    } catch {
        /* storage unavailable */
    }
}

/** Hashing needs a secure context (HTTPS or localhost); returns null where unavailable. */
export async function sha256Hex(text: string): Promise<string | null> {
    if (!window.crypto?.subtle) {
        return null;
    }
    const digest = await window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
    return Array.from(new Uint8Array(digest), (b) => b.toString(16).padStart(2, '0')).join('');
}

export const looksLikeTicketCode = (value: string) => /^tkt-/i.test(value.trim());

/**
 * Verifies a scan against the manifest and marks the ticket used locally, so a
 * second offline scan on this device is caught as a duplicate.
 */
export async function verifyOffline(slug: string, manifest: Manifest, input: { qr_token?: string; code?: string }): Promise<ScanOutcome> {
    const hash = input.qr_token ? await sha256Hex(input.qr_token) : null;
    const code = input.code?.trim().toUpperCase();
    const entry = manifest.tickets.find((t) => (hash && t.h === hash) || (code && t.c === code));

    if (!entry) {
        return { result: 'invalid', message: 'Not on this device’s ticket list. It may have been bought after the list was downloaded.', ticket: null };
    }

    const ticket = { code: entry.c, type: entry.t, attendee_name: entry.n };

    switch (entry.s) {
        case 'used':
            return { result: 'duplicate', message: `Already checked in${entry.u ? ` at ${new Date(entry.u).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : ''}.`, ticket };
        case 'void':
            return { result: 'void', message: 'This ticket has been voided or refunded.', ticket };
        case 'unpaid':
            return { result: 'unpaid', message: 'Payment for this ticket is not confirmed.', ticket };
        default:
            entry.s = 'used';
            entry.u = new Date().toISOString();
            saveManifest(slug, manifest);
            return { result: 'valid', message: 'Entry permitted (verified offline).', ticket };
    }
}

/** Keeps the local list in step with online scans, so switching to offline mid-event stays accurate. */
export function markUsedLocally(slug: string, manifest: Manifest | null, code: string | undefined): void {
    const entry = code ? manifest?.tickets.find((t) => t.c === code) : undefined;
    if (manifest && entry && entry.s === 'valid') {
        entry.s = 'used';
        entry.u = new Date().toISOString();
        saveManifest(slug, manifest);
    }
}
