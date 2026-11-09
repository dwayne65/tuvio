<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    protected $fillable = [
        'reference', 'organization_id', 'organization_payout_account_id',
        'amount_minor', 'currency', 'status', 'method', 'provider', 'provider_ref',
        'period_start', 'period_end', 'requested_at', 'processed_at', 'approved_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'requested_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function payoutAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationPayoutAccount::class, 'organization_payout_account_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
