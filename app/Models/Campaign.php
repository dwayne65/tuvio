<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Campaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id', 'event_id', 'title', 'slug', 'description', 'cover_image_path',
        'goal_minor', 'raised_minor', 'currency', 'contributors_count',
        'status', 'deadline_at', 'allow_anonymous', 'suggested_amounts_minor', 'commission_bps',
    ];

    protected function casts(): array
    {
        return [
            'goal_minor' => 'integer',
            'raised_minor' => 'integer',
            'contributors_count' => 'integer',
            'deadline_at' => 'datetime',
            'allow_anonymous' => 'boolean',
            'suggested_amounts_minor' => 'array',
            'commission_bps' => 'integer',
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

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Progress toward goal as a 0-100 percentage (0 when open-ended). */
    public function progressPercent(): int
    {
        if (! $this->goal_minor) {
            return 0;
        }

        return (int) min(100, round($this->raised_minor / $this->goal_minor * 100));
    }
}
