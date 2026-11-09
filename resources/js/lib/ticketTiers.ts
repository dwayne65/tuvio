import { Crown, LucideIcon, Sparkles, Star, Ticket } from 'lucide-react';

/** Ticket tiers an organizer can choose (mirrors TicketType::TIERS). Each gets its own ticket design. */
export type TicketTier = 'general' | 'vip' | 'vvip' | 'other';

export interface TierMeta {
    key: TicketTier;
    label: string;
    hint: string;
    icon: LucideIcon;
    presetName: string;
    /** Fixed swatch colours so the tier reads the same in day and night themes. */
    swatch: string;
    chip: string;
    suggestedPerks: string[];
}

export const TICKET_TIERS: TierMeta[] = [
    {
        key: 'general',
        label: 'General',
        hint: 'Standard entry for everyone',
        icon: Ticket,
        presetName: 'General Admission',
        swatch: 'linear-gradient(135deg, #8468FF, #4B32C3)',
        chip: 'bg-[#F0ECFF] text-[#5B3FD6]',
        suggestedPerks: ['Event entry', 'Access to all main stages', 'Free Wi-Fi'],
    },
    {
        key: 'vip',
        label: 'VIP',
        hint: 'Premium seats and perks',
        icon: Star,
        presetName: 'VIP',
        swatch: 'linear-gradient(135deg, #F3D58A, #B8862F 55%, #2A2114)',
        chip: 'bg-[#FBF1D9] text-[#8A6420]',
        suggestedPerks: ['Fast-track entry', 'Reserved seating', 'Complimentary drink', 'VIP lounge access'],
    },
    {
        key: 'vvip',
        label: 'VVIP',
        hint: 'The ultimate experience',
        icon: Crown,
        presetName: 'VVIP',
        swatch: 'linear-gradient(135deg, #FF7AD9, #9D8CFF 50%, #5EE7FF)',
        chip: 'bg-[#F6E9FF] text-[#7A3FB0]',
        suggestedPerks: ['Backstage access', 'Meet & greet', 'Private lounge', 'Premium open bar', 'Dedicated host'],
    },
    {
        key: 'other',
        label: 'Other',
        hint: 'Early bird, student, group…',
        icon: Sparkles,
        presetName: '',
        swatch: 'linear-gradient(135deg, #5EEAD4, #0EA5A4 55%, #134E4A)',
        chip: 'bg-[#DDF7F3] text-[#0F766E]',
        suggestedPerks: ['Discounted price', 'Limited availability', 'Valid student ID required'],
    },
];

export function tierMeta(tier: string | null | undefined): TierMeta {
    return TICKET_TIERS.find((t) => t.key === tier) ?? TICKET_TIERS[0];
}
