<?php

namespace App\Domain\Ussd;

use Illuminate\Support\Str;

/** The screen shown on the caller's handset, and whether the session stays open for more input. */
final class UssdReply
{
    private function __construct(
        public readonly string $state,
        public readonly string $message,
    ) {}

    public static function continue(string $message): self
    {
        return new self('CONTINUE', self::plain($message));
    }

    public static function end(string $message): self
    {
        return new self('END', self::plain($message));
    }

    /**
     * Telcos may fail to render non-ASCII characters (curly quotes, accents), so send plain ASCII.
     * Converted line by line because transliteration would also turn the menu's newlines into spaces.
     */
    private static function plain(string $message): string
    {
        return collect(explode("\n", $message))->map(fn (string $line) => Str::ascii($line))->implode("\n");
    }
}
