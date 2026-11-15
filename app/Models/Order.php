<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'reference', 'event_id', 'user_id', 'promo_code_id', 'promoter_id',
        'buyer_name', 'buyer_email', 'buyer_phone', 'status', 'currency',
        'subtotal_minor', 'discount_minor', 'fee_minor', 'tax_minor',
        'total_minor', 'commission_minor',
        'idempotency_key', 'reserved_until', 'paid_at', 'ip_address', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'fee_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'commission_minor' => 'integer',
            'reserved_until' => 'datetime',
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /** Trashed events keep their orders readable so refunds and ledger lookups still resolve. */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(EventReview::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function payoutItem(): HasOne
    {
        return $this->hasOne(PayoutItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /** Organizer net = subtotal - discount - commission (buyer-facing fees are platform income). */
    public function organizerNetMinor(): int
    {
        return $this->subtotal_minor - $this->discount_minor - $this->commission_minor - $this->organizerTaxMinor();
    }

    /** Tax on the platform fee is the organizer's to bear when they absorbed the fee as commission. */
    public function organizerTaxMinor(): int
    {
        return $this->commission_minor > 0 ? $this->tax_minor : 0;
    }

    /** Tax the buyer paid on top, alongside a booking fee. */
    public function buyerTaxMinor(): int
    {
        return $this->fee_minor > 0 ? $this->tax_minor : 0;
    }
}
