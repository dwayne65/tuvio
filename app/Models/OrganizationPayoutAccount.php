<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPayoutAccount extends Model
{
    protected $fillable = [
        'organization_id', 'type', 'provider', 'label',
        'account_name', 'account_number', 'bank_code', 'currency',
        'verification_status', 'verified_at', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'is_default' => 'boolean',
            // Sensitive bank/mobile-money details are encrypted at rest.
            'account_name' => 'encrypted',
            'account_number' => 'encrypted',
            'bank_code' => 'encrypted',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }
}
