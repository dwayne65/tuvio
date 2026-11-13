<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promoter extends Model
{
    protected $fillable = [
        'organization_id', 'event_id', 'name', 'email', 'code',
        'commission_bps', 'clicks', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'commission_bps' => 'integer',
            'clicks' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Whether this promoter may be credited for a sale of the given event. */
    public function covers(Event $event): bool
    {
        return $this->is_active
            && $this->organization_id === $event->organization_id
            && ($this->event_id === null || $this->event_id === $event->id);
    }
}
