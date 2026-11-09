import {
    Briefcase, Church, Cpu, GraduationCap, HeartHandshake, Music, PartyPopper,
    Sparkles, Trophy, type LucideIcon,
} from 'lucide-react';

// Explicit map so only these icons are bundled (no `import *`, keeps tree-shaking working).
const map: Record<string, LucideIcon> = {
    music: Music,
    briefcase: Briefcase,
    'graduation-cap': GraduationCap,
    trophy: Trophy,
    cpu: Cpu,
    'party-popper': PartyPopper,
    church: Church,
    'heart-handshake': HeartHandshake,
};

export function categoryIcon(icon: string | null | undefined): LucideIcon {
    return (icon && map[icon]) || Sparkles;
}
