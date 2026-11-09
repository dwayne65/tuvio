<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single debit or credit line. Immutable once written (enforced by a DB trigger
 * in PostgreSQL). Never update or delete rows of this model.
 */
class LedgerEntry extends Model
{
    protected $fillable = [
        'ledger_transaction_id', 'ledger_account_id',
        'direction', 'amount_minor', 'currency', 'memo',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'ledger_transaction_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }
}
