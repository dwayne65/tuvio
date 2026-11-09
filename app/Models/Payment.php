<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'reference', 'purpose', 'order_id', 'subscription_invoice_id', 'contribution_id',
        'provider', 'provider_ref', 'method', 'idempotency_key',
        'amount_minor', 'currency', 'processor_fee_minor', 'status',
        'provider_payload', 'authorized_at', 'verified_at', 'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'processor_fee_minor' => 'integer',
            'provider_payload' => 'array',
            'authorized_at' => 'datetime',
            'verified_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subscriptionInvoice(): BelongsTo
    {
        return $this->belongsTo(SubscriptionInvoice::class);
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** A payment is only trustworthy once verified server-side. */
    public function isVerified(): bool
    {
        return $this->status === 'successful' && $this->verified_at !== null;
    }
}
