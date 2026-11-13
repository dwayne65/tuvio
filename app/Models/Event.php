<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id', 'event_category_id', 'title', 'slug', 'description',
        'cover_image_path', 'status', 'visibility', 'type',
        'start_at', 'end_at', 'timezone',
        'venue_name', 'venue_address', 'city', 'country', 'latitude', 'longitude', 'online_url',
        'currency', 'commission_bps', 'fee_payer', 'refund_policy',
        'capacity', 'is_featured', 'published_at', 'sell_on_ussd',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'published_at' => 'datetime',
            'review_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_featured' => 'boolean',
            'sell_on_ussd' => 'boolean',
            'commission_bps' => 'integer',
            'capacity' => 'integer',
            'views_count' => 'integer',
        ];
    }

    /** Who covers the platform fee on paid tickets. */
    public const FEE_PAYERS = ['organizer', 'buyer'];

    /** The platform fee rate in basis points: event override, then the organizer's rate or plan. */
    public function commissionBps(): int
    {
        return (int) ($this->commission_bps ?? $this->organization->commissionBps());
    }

    public function passesFeeToBuyer(): bool
    {
        return $this->fee_payer === 'buyer';
    }

    /**
     * Split the platform fee for an amount after discounts. The organizer either absorbs it as
     * commission, or the buyer pays it on top as a booking fee and the organizer keeps the full price.
     *
     * Tax on the fee goes with it: the buyer pays it on a booking fee, the organizer on commission.
     *
     * @return array{commission_minor: int, fee_minor: int, tax_minor: int}
     */
    public function platformFee(int $amountMinor): array
    {
        $fee = (int) round(max(0, $amountMinor) * $this->commissionBps() / 10000);
        $tax = (int) round($fee * (int) config('ticketflow.tax.bps') / 10000);

        return $this->passesFeeToBuyer()
            ? ['commission_minor' => 0, 'fee_minor' => $fee, 'tax_minor' => $tax]
            : ['commission_minor' => $fee, 'fee_minor' => 0, 'tax_minor' => $tax];
    }

    /** What a buyer pays on top of the tickets: the booking fee and its tax, when the fee is passed on. */
    public function buyerCharges(int $amountMinor): int
    {
        ['fee_minor' => $fee, 'tax_minor' => $tax] = $this->platformFee($amountMinor);

        return $this->passesFeeToBuyer() ? $fee + $tax : 0;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventCategory::class, 'event_category_id');
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(EventScheduleItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(EventStaff::class);
    }

    public function gates(): HasMany
    {
        return $this->hasMany(EventGate::class)->orderBy('sort')->orderBy('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(EventReview::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Public URL for the cover image (handles stored paths and external URLs). */
    public function getCoverUrlAttribute(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return str_starts_with($this->cover_image_path, 'http')
            ? $this->cover_image_path
            : Storage::disk('public')->url($this->cover_image_path);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Submitted by the organizer and waiting for a platform admin to approve it. */
    public function isInReview(): bool
    {
        return $this->status === 'in_review';
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Publicly discoverable events. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('visibility', 'public');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_at', '>=', now());
    }
}
