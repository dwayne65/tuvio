<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A named entrance (e.g. "Main gate", "VIP entrance") where tickets are scanned. */
class EventGate extends Model
{
    protected $fillable = ['event_id', 'name', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
