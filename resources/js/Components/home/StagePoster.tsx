import { cn } from '@/lib/utils';
import { useMemo } from 'react';

/**
 * The concert as a lightweight CSS/SVG picture: sweeping beams, haze and a
 * crowd skyline. Shown on phones, while the 3D scene loads, and wherever
 * WebGL is unavailable. Beams stop moving under reduced motion.
 */
export function StagePoster({ className }: { className?: string }) {
    const skyline = useMemo(() => crowdPath(), []);

    return (
        <div aria-hidden className={cn('absolute inset-0 overflow-hidden bg-[#0b0a1f]', className)}>
            {/* Haze glow behind the crowd */}
            <div className="absolute inset-x-0 bottom-[18%] h-[60%] bg-[radial-gradient(60%_70%_at_55%_100%,rgb(124_58_237/0.55),transparent_70%)]" />
            <div className="absolute inset-x-0 bottom-[22%] h-[40%] bg-[radial-gradient(40%_60%_at_70%_100%,rgb(192_75_255/0.3),transparent_70%)]" />

            {/* Beams */}
            {[
                { left: '18%', delay: '0s', color: 'rgb(203 184 255 / 0.28)' },
                { left: '38%', delay: '-3s', color: 'rgb(124 58 237 / 0.38)' },
                { left: '62%', delay: '-6s', color: 'rgb(203 184 255 / 0.24)' },
                { left: '82%', delay: '-1.5s', color: 'rgb(192 75 255 / 0.32)' },
            ].map((beam) => (
                <div
                    key={beam.left}
                    className="poster-beam absolute top-[-10%] h-[95%] w-[22vw] max-w-72 origin-top"
                    style={{ left: beam.left, animationDelay: beam.delay, background: `linear-gradient(to bottom, ${beam.color}, transparent 85%)` }}
                />
            ))}

            {/* Crowd skyline with a few phone lights */}
            <svg className="absolute inset-x-0 bottom-0 h-[38%] w-full" viewBox="0 0 1200 300" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="poster-crowd" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stopColor="#1a1238" />
                        <stop offset="1" stopColor="#050409" />
                    </linearGradient>
                </defs>
                <path d={skyline.back} fill="#130d2b" opacity="0.8" />
                <path d={skyline.front} fill="url(#poster-crowd)" />
                {skyline.lights.map(([x, y], i) => (
                    <circle key={i} cx={x} cy={y} r="2.2" fill="#f2ecff" className="poster-twinkle" style={{ animationDelay: `${(i % 7) * -0.6}s` }} />
                ))}
            </svg>

            {/* Glowing stage edge */}
            <div className="absolute inset-x-0 bottom-[6%] h-px bg-gradient-to-r from-transparent via-[#a78bfa] to-transparent shadow-[0_0_24px_4px_rgb(124_58_237/0.6)]" />
        </div>
    );
}

/** Heads-and-shoulders silhouettes as two SVG paths, plus raised phone lights. */
function crowdPath() {
    let seed = 5;
    const rand = () => ((seed = (seed * 16807) % 2147483647) - 1) / 2147483646;
    const row = (baseline: number, size: number) => {
        let d = `M0 300 L0 ${baseline}`;
        for (let x = 0; x < 1200; x += size * (0.9 + rand() * 0.5)) {
            const h = baseline - size * (0.6 + rand() * 0.5);
            d += ` Q${x + size * 0.15} ${h + size * 0.35} ${x + size * 0.5} ${h} Q${x + size * 0.85} ${h + size * 0.35} ${x + size} ${baseline - size * 0.15}`;
        }
        return `${d} L1200 300 Z`;
    };
    const lights = Array.from({ length: 22 }, () => [rand() * 1200, 120 + rand() * 90] as [number, number]);

    return { back: row(150, 26), front: row(215, 42), lights };
}
