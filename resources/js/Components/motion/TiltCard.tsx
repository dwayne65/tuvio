import { cn } from '@/lib/utils';
import { motion, useMotionValue, useSpring, useTransform } from 'framer-motion';
import { PointerEvent, ReactNode, useRef } from 'react';

/**
 * A card that tilts in 3D toward the cursor with a soft glare — subtle depth,
 * not a gimmick. Respects reduced-motion (falls back to a flat card).
 */
export function TiltCard({
    children,
    className,
    max = 8,
}: {
    children: ReactNode;
    className?: string;
    max?: number;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const px = useMotionValue(0.5);
    const py = useMotionValue(0.5);

    const rotateX = useSpring(useTransform(py, [0, 1], [max, -max]), { stiffness: 220, damping: 18 });
    const rotateY = useSpring(useTransform(px, [0, 1], [-max, max]), { stiffness: 220, damping: 18 });
    const glareX = useTransform(px, [0, 1], ['0%', '100%']);

    const prefersReduced = typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    function onMove(e: PointerEvent<HTMLDivElement>) {
        if (prefersReduced || !ref.current) return;
        const r = ref.current.getBoundingClientRect();
        px.set((e.clientX - r.left) / r.width);
        py.set((e.clientY - r.top) / r.height);
    }
    function reset() {
        px.set(0.5);
        py.set(0.5);
    }

    return (
        <motion.div
            ref={ref}
            onPointerMove={onMove}
            onPointerLeave={reset}
            style={prefersReduced ? undefined : { rotateX, rotateY, transformPerspective: 900, transformStyle: 'preserve-3d' }}
            className={cn('relative', className)}
        >
            {children}
            {!prefersReduced && (
                <motion.span
                    aria-hidden
                    className="pointer-events-none absolute inset-0 rounded-[inherit] opacity-0 transition-opacity duration-300 [mask-image:radial-gradient(60%_60%_at_var(--gx)_50%,black,transparent)] hover:opacity-100"
                    style={{ ['--gx' as any]: glareX, background: 'linear-gradient(120deg, transparent, rgba(255,255,255,0.35), transparent)' }}
                />
            )}
        </motion.div>
    );
}
