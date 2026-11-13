<?php

namespace App\Domain\Orders;

use App\Models\Event;
use App\Models\PromoCode;
use RuntimeException;

/** Looks up promo codes for an event and works out how much they take off an order. */
class Discounts
{
    /**
     * Find a redeemable code for the event, or throw with a buyer-facing reason.
     * Pass $lock inside a transaction to serialise redemptions of the same code.
     */
    public function resolve(Event $event, string $code, bool $lock = false): PromoCode
    {
        $promo = PromoCode::query()
            ->where('organization_id', $event->organization_id)
            ->where('code', strtoupper(trim($code)))
            ->where(fn ($q) => $q->whereNull('event_id')->orWhere('event_id', $event->id))
            ->when($lock, fn ($q) => $q->lockForUpdate())
            ->first();

        // Codes stop working when the organizer's plan no longer includes promo codes.
        if (! $promo || ! $promo->isRedeemable() || ! $event->organization->planAllows('promo_codes')) {
            throw new RuntimeException('This promo code is invalid or has expired.');
        }

        return $promo;
    }

    /** Discount in minor units for a subtotal; never more than the subtotal itself. */
    public function amount(PromoCode $promo, int $subtotalMinor, string $currency): int
    {
        if ($promo->min_order_minor !== null && $subtotalMinor < $promo->min_order_minor) {
            throw new RuntimeException('Your order is below the minimum for this promo code.');
        }

        if ($promo->type === 'fixed') {
            if ($promo->currency !== null && $promo->currency !== $currency) {
                throw new RuntimeException('This promo code cannot be used for this event.');
            }

            return min($promo->value, $subtotalMinor);
        }

        // Percent codes store basis points (10% = 1000).
        return min((int) round($subtotalMinor * $promo->value / 10000), $subtotalMinor);
    }
}
