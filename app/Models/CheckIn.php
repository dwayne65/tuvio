<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    protected $fillable = [
        'event_id', 'ticket_id', 'scanned_by', 'result',
        'gate', 'device_id', 'client_uuid', 'scanned_at', 'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
