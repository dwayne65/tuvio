<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    /** Ticket tiers an organizer can choose; each gets its own ticket design. */
    public const TIERS = ['general', 'vip', 'vvip', 'other'];

    protected $fillable = [
        'event_id', 'name', 'tier', 'kind', 'description', 'perks', 'price_minor', 'currency',
        'quantity_total', 'quantity_sold', 'quantity_reserved',
        'min_per_order', 'max_per_order', 'sales_start_at', 'sales_end_at',
        'status', 'is_hidden', 'sort',
    ];

    protected function casts(): array
    {
        return [
            'price_minor' => 'integer',
            'quantity_total' => 'integer',
            'quantity_sold' => 'integer',
            'quantity_reserved' => 'integer',
            'min_per_order' => 'integer',
            'max_per_order' => 'integer',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'is_hidden' => 'boolean',
            'perks' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** The chosen tier, or one inferred from the name for types created before tiers existed. */
    public function resolvedTier(): string
    {
        if ($this->tier) {
            return $this->tier;
        }

        $name = strtolower($this->name);

        return match (true) {
            (bool) preg_match('/vvip|v\.v\.i\.p|platinum|diamond/', $name) => 'vvip',
            (bool) preg_match('/vip|v\.i\.p|gold|premium/', $name) => 'vip',
            default => 'general',
        };
    }

    public function isFree(): bool
    {
        return $this->kind === 'free' || $this->price_minor === 0;
    }

    /** Remaining tickets available for sale (null = unlimited). */
    public function availableQuantity(): ?int
    {
        if ($this->quantity_total === null) {
            return null;
        }

        return max(0, $this->quantity_total - $this->quantity_sold - $this->quantity_reserved);
    }

    public function isOnSale(): bool
    {
        $now = now();

        return $this->status === 'active'
            && ! $this->is_hidden
            && ($this->sales_start_at === null || $this->sales_start_at->lte($now))
            && ($this->sales_end_at === null || $this->sales_end_at->gte($now))
            && ($this->availableQuantity() === null || $this->availableQuantity() > 0);
    }
}
