import { Crown, Gem, LucideIcon, Sparkles, Star, Ticket } from 'lucide-react';

/** Ticket tiers an organizer can choose (mirrors TicketType::TIERS). Each gets its own ticket design (App\Support\TicketDesign). */
export type TicketTier = 'general' | 'vip' | 'vvip' | 'guest' | 'other';

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
        label: 'Ordinary',
        hint: 'Standard entry for everyone',
        icon: Ticket,
        presetName: 'Ordinary',
        swatch: 'linear-gradient(135deg, #1A2133, #0D1017 60%, #5B93FF)',
        chip: 'bg-[#E8EFFF] text-[#2F5BC4]',
        suggestedPerks: ['Event entry', 'Access to all main stages', 'Free Wi-Fi'],
    },
    {
        key: 'vip',
        label: 'VIP',
        hint: 'Premium seats and perks',
        icon: Star,
        presetName: 'VIP',
        swatch: 'linear-gradient(135deg, #F7E2A6, #B98B3B 50%, #0B0906)',
        chip: 'bg-[#FBF1D9] text-[#8A6420]',
        suggestedPerks: ['Fast-track entry', 'Reserved seating', 'Complimentary drink', 'VIP lounge access'],
    },
    {
        key: 'vvip',
        label: 'VVIP',
        hint: 'The ultimate experience',
        icon: Crown,
        presetName: 'VVIP',
        swatch: 'linear-gradient(135deg, #F7F7FA, #7E808B 45%, #060608 70%, #B8955A)',
        chip: 'bg-[#EDEDF1] text-[#3B3D47]',
        suggestedPerks: ['Backstage access', 'Meet & greet', 'Private lounge', 'Premium open bar', 'Dedicated host'],
    },
    {
        key: 'guest',
        label: 'Guest',
        hint: 'Invited guests, press, complimentary',
        icon: Gem,
        presetName: 'Guest List',
        swatch: 'linear-gradient(135deg, #F7F2E8, #E9E0CC 50%, #1A2A4C)',
        chip: 'bg-[#EEF1F7] text-[#1F3A6B]',
        suggestedPerks: ['Complimentary entry', 'Name on the guest list', 'Bring valid ID'],
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
