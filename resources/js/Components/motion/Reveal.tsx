import { fadeUp, stagger } from '@/lib/motion';
import { motion } from 'framer-motion';
import { ReactNode } from 'react';

/** Reveals children on scroll into view, with an optional stagger. */
export function Reveal({
    children,
    className,
    delay = 0,
    as = 'div',
}: {
    children: ReactNode;
    className?: string;
    delay?: number;
    as?: 'div' | 'section';
}) {
    const Comp = as === 'section' ? motion.section : motion.div;
    return (
        <Comp
            className={className}
            variants={fadeUp}
            initial="hidden"
            whileInView="show"
            viewport={{ once: true, margin: '-60px' }}
            transition={{ delay }}
        >
            {children}
        </Comp>
    );
}

/** Staggered grid/list container; pair with <RevealItem>. */
export function RevealGroup({ children, className, gap }: { children: ReactNode; className?: string; gap?: number }) {
    return (
        <motion.div
            className={className}
            variants={stagger(gap)}
            initial="hidden"
            whileInView="show"
            viewport={{ once: true, margin: '-60px' }}
        >
            {children}
        </motion.div>
    );
}

export function RevealItem({ children, className }: { children: ReactNode; className?: string }) {
    return (
        <motion.div className={className} variants={fadeUp}>
            {children}
        </motion.div>
    );
}
