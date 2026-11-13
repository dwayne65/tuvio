<?php

namespace App\Domain\Sms;

final class SmsResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly ?string $messageId = null,
        public readonly ?string $error = null,
    ) {}
}
