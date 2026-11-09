<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerAccount extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'normal_balance',
        'owner_type', 'owner_id', 'currency', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * Current balance in minor units, signed according to the account's normal balance.
     * Asset/expense accounts increase on debit; liability/income/equity on credit.
     */
    public function balanceMinor(): int
    {
        $debits = (int) $this->entries()->where('direction', 'debit')->sum('amount_minor');
        $credits = (int) $this->entries()->where('direction', 'credit')->sum('amount_minor');

        return $this->normal_balance === 'debit'
            ? $debits - $credits
            : $credits - $debits;
    }
}
