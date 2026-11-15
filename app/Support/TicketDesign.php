<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Support\Str;

/**
 * The ticket design system: one entry per ticket category, shared by the PDF, the
 * printable sheet, the email and the wallet. A ticket type's tier picks the design
 * (General and Other use Ordinary; Other keeps the organizer's own name as its label).
 * To add a category, add an entry to DESIGNS and map its tier in designKey().
 */
class TicketDesign
{
    /**
     * Colours are hex for CSS/HTML and RGB triples where the PDF artwork is rasterised.
     *
     * pattern: grid | deco | guilloche | pinstripe (drawn by TicketArtwork and the wallet).
     * holo: an iridescent seal and sheen (premium tiers only).
     *
     * @var array<string, array{label: string, badge: string, glyph: string, privilege: string, dark: bool, pattern: string, holo: bool, base: string, base2: string, ink: string, muted: string, accent: string, accent2: string, metal: array{string, string, string}, stubFrom: string, stubTo: string, stubInk: string, stubMuted: string, rule: string}>
     */
    private const DESIGNS = [
        'ordinary' => [
            'label' => 'ORDINARY', 'badge' => 'Ordinary admission', 'glyph' => '◆', 'privilege' => 'General admission',
            'dark' => true, 'pattern' => 'grid', 'holo' => false,
            'base' => '#0D1017', 'base2' => '#1A2133', 'ink' => '#F4F6FB', 'muted' => '#9AA3B8',
            'accent' => '#5B93FF', 'accent2' => '#E6D3A3',
            'metal' => ['#3A6BD6', '#BFD6FF', '#5B93FF'],
            'stubFrom' => '#161C2B', 'stubTo' => '#090B11', 'stubInk' => '#FFFFFF', 'stubMuted' => '#9AA3B8',
            'rule' => '#FFFFFF',
        ],
        'vip' => [
            'label' => 'VIP', 'badge' => 'VIP admission', 'glyph' => '★', 'privilege' => 'VIP lounge & priority entry',
            'dark' => true, 'pattern' => 'deco', 'holo' => true,
            'base' => '#0B0906', 'base2' => '#211A0E', 'ink' => '#FFF8E7', 'muted' => '#C2AF86',
            'accent' => '#D9B566', 'accent2' => '#F6E4B5',
            'metal' => ['#8A6726', '#F7E2A6', '#B98B3B'],
            'stubFrom' => '#231B0D', 'stubTo' => '#080604', 'stubInk' => '#FBF1D8', 'stubMuted' => '#C2AF86',
            'rule' => '#D9B566',
        ],
        'vvip' => [
            'label' => 'VVIP', 'badge' => 'VVIP · All access', 'glyph' => '♛', 'privilege' => 'All access & private lounge',
            'dark' => true, 'pattern' => 'guilloche', 'holo' => true,
            'base' => '#060608', 'base2' => '#17171E', 'ink' => '#FFFFFF', 'muted' => '#A6A8B4',
            'accent' => '#E7E7EC', 'accent2' => '#B8955A',
            'metal' => ['#7E808B', '#F7F7FA', '#B8955A'],
            'stubFrom' => '#1B1B23', 'stubTo' => '#040405', 'stubInk' => '#FFFFFF', 'stubMuted' => '#A6A8B4',
            'rule' => '#E7E7EC',
        ],
        'guest' => [
            'label' => 'GUEST', 'badge' => 'Guest admission', 'glyph' => '✧', 'privilege' => 'Guest of the organizer',
            'dark' => false, 'pattern' => 'pinstripe', 'holo' => false,
            'base' => '#F7F2E8', 'base2' => '#E9E0CC', 'ink' => '#14213D', 'muted' => '#69728A',
            'accent' => '#1F3A6B', 'accent2' => '#9AA2B3',
            'metal' => ['#7F8798', '#EEF0F4', '#8E96A6'],
            'stubFrom' => '#1A2A4C', 'stubTo' => '#0B1428', 'stubInk' => '#F7F2E8', 'stubMuted' => '#A9B3CA',
            'rule' => '#14213D',
        ],
    ];

    /** The design key for a ticket type's tier. */
    public static function designKey(TicketType $type): string
    {
        return match ($type->resolvedTier()) {
            'vip' => 'vip',
            'vvip' => 'vvip',
            'guest' => 'guest',
            default => 'ordinary',
        };
    }

    /**
     * The full design for one ticket: the category design plus what depends on the ticket
     * itself (the label for "Other" tiers, and whether admission is complimentary).
     *
     * @return array<string, mixed>
     */
    public static function forTicket(Ticket $ticket): array
    {
        $type = $ticket->ticketType;
        $key = self::designKey($type);
        $design = self::DESIGNS[$key];
        $priceMinor = $ticket->orderItem?->unit_price_minor ?? $type->price_minor;
        $complimentary = (int) $priceMinor === 0;

        $label = $type->resolvedTier() === 'other'
            ? Str::upper(Str::limit($type->name, 14, ''))
            : $design['label'];

        return [
            ...$design,
            'key' => $key,
            'label' => $label,
            'badge' => $key === 'guest' && $complimentary ? 'Complimentary guest' : $design['badge'],
            'complimentary' => $complimentary,
        ];
    }

    /**
     * The tokens the wallet needs (no rasterising data).
     *
     * @return array<string, mixed>
     */
    public static function forWallet(Ticket $ticket): array
    {
        return collect(self::forTicket($ticket))->only([
            'key', 'label', 'badge', 'glyph', 'privilege', 'dark', 'pattern', 'holo', 'complimentary',
            'base', 'base2', 'ink', 'muted', 'accent', 'accent2', 'metal', 'stubFrom', 'stubTo', 'stubInk', 'stubMuted', 'rule',
        ])->all();
    }

    /** @return array{int, int, int} */
    public static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
