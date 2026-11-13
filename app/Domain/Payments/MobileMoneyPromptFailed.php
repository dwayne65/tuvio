<?php

namespace App\Domain\Payments;

use RuntimeException;

/** The provider did not accept the Mobile Money PIN prompt, so nothing is waiting on the buyer's phone. */
class MobileMoneyPromptFailed extends RuntimeException
{
    public function __construct(public readonly string $providerRef, string $message)
    {
        parent::__construct($message);
    }
}
