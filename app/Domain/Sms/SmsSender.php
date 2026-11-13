<?php

namespace App\Domain\Sms;

/** Sends a text message to one phone number. */
interface SmsSender
{
    /** @param string $to E.164 number, e.g. +256772123456 */
    public function send(string $to, string $message): SmsResult;
}
