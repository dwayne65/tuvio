import type { Variants } from 'framer-motion';

/** Shared motion variants for a consistent, premium feel across the app. */

export const fadeUp: Variants = {
    hidden: { opacity: 0, y: 16 },
    show: { opacity: 1, y: 0, transition: { duration: 0.5, ease: [0.22, 1, 0.36, 1] } },
};

export const fade: Variants = {
    hidden: { opacity: 0 },
    show: { opacity: 1, transition: { duration: 0.4 } },
};

export const scaleIn: Variants = {
    hidden: { opacity: 0, scale: 0.96 },
    show: { opacity: 1, scale: 1, transition: { duration: 0.4, ease: [0.22, 1, 0.36, 1] } },
};

/** Container that staggers its children in. */
export const stagger = (gap = 0.07): Variants => ({
    hidden: {},
    show: { transition: { staggerChildren: gap } },
});

export const spring = { type: 'spring', stiffness: 260, damping: 24 } as const;
