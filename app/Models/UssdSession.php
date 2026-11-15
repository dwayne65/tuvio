<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One caller's visit to the USSD menu, from dialing in to the last screen. */
class UssdSession extends Model
{
    public const OUTCOME_ORDER = 'order';

    public const OUTCOME_CONTRIBUTION = 'contribution';

    public const OUTCOME_TICKETS_VIEWED = 'tickets_viewed';

    public const OUTCOME_TICKETS_SENT = 'tickets_sent';

    public const OUTCOME_CANCELLED = 'cancelled';

    public const OUTCOME_FAILED = 'failed';

    public $timestamps = false;

    protected $fillable = [
        'session_id', 'msisdn', 'shortcode', 'network', 'event_id', 'dialed_direct',
        'last_step', 'screens', 'outcome', 'order_id', 'contribution_id', 'started_at', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'dialed_direct' => 'boolean',
            'screens' => 'integer',
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * Mobile networks by MCC-MNC, as the gateway reports the caller's SIM.
     *
     * @var array<string, string>
     */
    public const NETWORKS = [
        '64101' => 'Airtel', '64122' => 'Airtel', '64110' => 'MTN', '64111' => 'Uganda Telecom',
        '64114' => 'Africell', '64118' => 'Smart', '64133' => 'Smile', '64166' => 'i-Tel',
    ];

    public static function networkFor(?string $mccmnc): ?string
    {
        return $mccmnc ? (self::NETWORKS[$mccmnc] ?? $mccmnc) : null;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }
}
