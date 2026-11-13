<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Earnings (commission, booking fees, plan payments) the platform owner took out. */
class PlatformWithdrawal extends Model
{
    protected $fillable = [
        'reference', 'amount_minor', 'currency', 'method', 'destination',
        'provider_ref', 'note', 'created_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
