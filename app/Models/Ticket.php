<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'event_id', 'ticket_type_id', 'order_id', 'order_item_id',
        'code', 'qr_token', 'attendee_name', 'attendee_email', 'seat_info',
        'status', 'issued_at', 'used_at', 'checked_in_by',
    ];

    protected $hidden = ['qr_token'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /** Trashed events keep their tickets readable for buyers and the gate. */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function isUsed(): bool
    {
        return $this->status === 'used';
    }

    public function isValid(): bool
    {
        return $this->status === 'issued';
    }
}
