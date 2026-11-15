import QRCode from 'qrcode';
import { useEffect, useState } from 'react';

/** Renders a value as a QR code image (generated client-side). */
export function Qr({ value, size = 180 }: { value: string; size?: number }) {
    const [src, setSrc] = useState<string>('');

    useEffect(() => {
        let active = true;
        // Drawn at twice the display size so it stays crisp on high-density phone screens.
        QRCode.toDataURL(value, { width: size * 2, margin: 1, color: { dark: '#111827', light: '#ffffff' } })
            .then((url) => active && setSrc(url))
            .catch(() => active && setSrc(''));
        return () => {
            active = false;
        };
    }, [value, size]);

    return src ? (
        <img src={src} width={size} height={size} alt="Ticket QR code" className="rounded-lg" />
    ) : (
        <div style={{ width: size, height: size }} className="animate-pulse rounded-lg bg-slate-100" />
    );
}
