<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerTransaction extends Model
{
    protected $fillable = [
        'reference', 'type', 'description',
        'source_type', 'source_id', 'currency', 'occurred_at', 'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** A posted transaction must balance: sum(debits) === sum(credits). */
    public function isBalanced(): bool
    {
        $debits = (int) $this->entries()->where('direction', 'debit')->sum('amount_minor');
        $credits = (int) $this->entries()->where('direction', 'credit')->sum('amount_minor');

        return $debits === $credits;
    }
}
