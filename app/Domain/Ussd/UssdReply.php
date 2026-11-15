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
        $message = collect(explode("\n", $message))->map(fn (string $line) => Str::ascii($line))->implode("\n");

        return self::fit($message, (int) config('ticketflow.ussd.max_screen_length', 182));
    }

    /**
     * Telcos drop or cut screens over their length limit. Drop whole lines from the middle
     * so the heading and the closing options (Back, Cancel) stay readable.
     */
    private static function fit(string $message, int $limit): string
    {
        $lines = explode("\n", $message);
        while (strlen(implode("\n", $lines)) > $limit && count($lines) > 2) {
            array_splice($lines, count($lines) - 2, 1);
        }

        return substr(implode("\n", $lines), 0, $limit);
    }
}
