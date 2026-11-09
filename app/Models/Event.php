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
        'currency', 'commission_bps', 'refund_policy',
        'capacity', 'is_featured', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'published_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_featured' => 'boolean',
            'commission_bps' => 'integer',
            'capacity' => 'integer',
            'views_count' => 'integer',
        ];
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
