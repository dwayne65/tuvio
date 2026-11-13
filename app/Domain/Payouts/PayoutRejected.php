<?php

namespace App\Domain\Payouts;

use RuntimeException;

/**
 * Paytota refused the payout before any money moved (e.g. insufficient balance), so it
 * is safe to send again later. Distinct from a plain RuntimeException, where the outcome
 * of the transfer is unknown and the payout must stay in processing until confirmed.
 */
class PayoutRejected extends RuntimeException
{
    public function __construct(string $message, public readonly ?string $errorCode = null)
    {
        parent::__construct($message);
    }
}
