<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contribution extends Model
{
    protected $fillable = [
        'reference', 'campaign_id', 'user_id', 'contributor_name', 'contributor_email',
        'is_anonymous', 'message', 'amount_minor', 'currency', 'commission_minor',
        'status', 'payment_id', 'receipt_number', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'amount_minor' => 'integer',
            'commission_minor' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payoutItem(): HasOne
    {
        return $this->hasOne(PayoutItem::class);
    }

    public function displayName(): string
    {
        return $this->is_anonymous ? 'Anonymous' : ($this->contributor_name ?: 'Supporter');
    }
}
