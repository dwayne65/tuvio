<?php

namespace App\Support;

use App\Models\Ticket;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Presentation-only artwork for the PDF ticket (tier palette, gradients and the
 * tinted event photo). DomPDF cannot draw CSS/SVG gradients or crop images,
 * so those layers are pre-rendered here as data URIs for the Blade view.
 */
class TicketArtwork
{
    /** Pixels per PDF point, so rasters stay sharp when printed. */
    private const SCALE = 2;

    /** Smooth gradients are computed at 1/DETAIL resolution and resampled up (keeps rendering fast). */
    private const DETAIL = 4;

    /**
     * @var array<string, array{label: string, glyph: string, panel: string, ink: string, muted: string, accent: string, title: string, stubInk: string, stubMuted: string, tierText: string, perforation: string, from: array{int,int,int}, to: array{int,int,int}, glows: list<array{array{int,int,int}, float, float, float, float}>, tint: array{int,int,int}, holo: bool}>
     */
    private const TIERS = [
        'standard' => [
            'label' => 'STANDARD', 'glyph' => '✦',
            'panel' => '#FFFFFF', 'ink' => '#171C2B', 'muted' => '#6B7280', 'accent' => '#7655F7', 'title' => '#7655F7',
            'stubInk' => '#FFFFFF', 'stubMuted' => '#DCD4FF', 'tierText' => '#FFFFFF', 'perforation' => '#FFFFFF',
            'from' => [132, 104, 255], 'to' => [75, 50, 195], 'glows' => [], 'tint' => [118, 85, 247], 'holo' => false,
        ],
        'vip' => [
            'label' => 'VIP', 'glyph' => '★',
            'panel' => '#FBF7EE', 'ink' => '#1A1508', 'muted' => '#7A6A48', 'accent' => '#C9973B', 'title' => '#B8862F',
            'stubInk' => '#F7EBD0', 'stubMuted' => '#CDB98C', 'tierText' => '#E9C46A', 'perforation' => '#E9C46A',
            'from' => [42, 33, 20], 'to' => [11, 10, 8], 'glows' => [[[233, 196, 106], 1.0, 0.0, 0.9, 0.35]], 'tint' => [201, 151, 59], 'holo' => false,
        ],
        'vvip' => [
            'label' => 'VVIP', 'glyph' => '♛',
            'panel' => '#0C0C12', 'ink' => '#FFFFFF', 'muted' => '#A1A1B5', 'accent' => '#9D8CFF', 'title' => '#E58BE8',
            'stubInk' => '#FFFFFF', 'stubMuted' => '#B8B8CC', 'tierText' => '#C6BCFF', 'perforation' => '#FFFFFF',
            'from' => [14, 13, 24], 'to' => [8, 8, 13],
            'glows' => [[[157, 140, 255], 1.0, 0.0, 0.9, 0.55], [[255, 122, 217], 0.0, 1.0, 0.8, 0.4]],
            'tint' => [255, 122, 217], 'holo' => true,
        ],
    ];

    /** @var array<string, string> */
    private array $cache = [];

    /** @var array<string, GdImage> */
    private array $tintLayers = [];

    /**
     * Ticket design for the ticket type's tier. General and Other share the standard
     * look; the stub label is the tier name, or the organizer's own name for Other.
     *
     * @return array<string, mixed>
     */
    public function tier(Ticket $ticket): array
    {
        $tier = $ticket->ticketType->resolvedTier();
        $key = in_array($tier, ['vip', 'vvip'], true) ? $tier : 'standard';

        $label = match ($tier) {
            'general' => 'GENERAL',
            'other' => Str::upper(Str::limit($ticket->ticketType->name, 12, '')),
            default => self::TIERS[$key]['label'],
        };

        return ['key' => $key, ...self::TIERS[$key], 'label' => $label];
    }

    /** Tear-off stub background in the tier gradient. */
    public function stub(array $tier, int $width, int $height): string
    {
        return $this->cache["stub.{$tier['key']}.{$width}x{$height}"] ??= $this->png(
            $this->gradient($width * self::SCALE, $height * self::SCALE, $tier['from'], $tier['to'], $tier['glows'])
        );
    }

    /** Thin holographic strip along the top of VVIP tickets. */
    public function holoStrip(int $width, int $height): string
    {
        return $this->cache["holo.{$width}x{$height}"] ??= $this->png(
            $this->gradient($width * self::SCALE, $height * self::SCALE, [255, 122, 217], [94, 231, 255], [[[157, 140, 255], 0.5, 0.5, 0.35, 0.9]], horizontal: true)
        );
    }

    /** The event cover cropped to fill the photo slot and washed in the tier tint (or a tier gradient fallback). */
    public function photo(Ticket $ticket, array $tier, int $width, int $height): string
    {
        $w = $width * self::SCALE;
        $h = $height * self::SCALE;
        $cover = $this->loadCover($ticket->event->cover_image_path);

        if (! $cover) {
            return $this->cache["fallback.{$tier['key']}.{$width}x{$height}"] ??= $this->png(
                $this->gradient($w, $h, [17, 24, 39], [8, 8, 13], [[$tier['tint'], 0.9, 0.0, 1.0, 0.75]])
            );
        }

        $photo = imagecreatetruecolor($w, $h);
        $scale = max($w / imagesx($cover), $h / imagesy($cover));
        $srcW = (int) round($w / $scale);
        $srcH = (int) round($h / $scale);
        imagecopyresampled($photo, $cover, 0, 0, (int) ((imagesx($cover) - $srcW) / 2), (int) ((imagesy($cover) - $srcH) / 2), $w, $h, $srcW, $srcH);

        imagealphablending($photo, true);
        imagecopy($photo, $this->tintLayers["{$tier['key']}.{$w}x{$h}"] ??= $this->tintLayer($tier['tint'], $w, $h), 0, 0, 0, 0, $w, $h);

        ob_start();
        imagejpeg($photo, null, 86);

        return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
    }

    private function loadCover(?string $path): ?GdImage
    {
        // Remote covers are skipped: the PDF never fetches external URLs.
        if (! $path || str_starts_with($path, 'http') || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $image = @imagecreatefromstring((string) Storage::disk('public')->get($path));

        return $image ?: null;
    }

    /**
     * Diagonal two-colour gradient with optional soft radial glows.
     *
     * @param  array{int,int,int}  $from
     * @param  array{int,int,int}  $to
     * @param  list<array{array{int,int,int}, float, float, float, float}>  $glows  [colour, x, y, radius, strength] in 0..1 units
     */
    private function gradient(int $width, int $height, array $from, array $to, array $glows = [], bool $horizontal = false): GdImage
    {
        $w = max(2, intdiv($width, self::DETAIL));
        $h = max(2, intdiv($height, self::DETAIL));
        $image = imagecreatetruecolor($w, $h);
        $span = max($w, $h);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $t = $horizontal ? $x / $w : ($x / $w + $y / $h) / 2;
                $c = [$from[0] + ($to[0] - $from[0]) * $t, $from[1] + ($to[1] - $from[1]) * $t, $from[2] + ($to[2] - $from[2]) * $t];

                foreach ($glows as [$glow, $gx, $gy, $radius, $strength]) {
                    $d = sqrt(($x - $gx * $w) ** 2 + ($y - $gy * $h) ** 2) / ($radius * $span);
                    $k = $strength * max(0, 1 - $d) ** 2;
                    $c = [$c[0] + ($glow[0] - $c[0]) * $k, $c[1] + ($glow[1] - $c[1]) * $k, $c[2] + ($glow[2] - $c[2]) * $k];
                }

                imagesetpixel($image, $x, $y, ((int) $c[0] << 16) | ((int) $c[1] << 8) | (int) $c[2]);
            }
        }

        return $this->upscale($image, $width, $height);
    }

    /** @param array{int,int,int} $tint */
    private function tintLayer(array $tint, int $width, int $height): GdImage
    {
        $w = max(2, intdiv($width, self::DETAIL));
        $h = max(2, intdiv($height, self::DETAIL));
        $layer = imagecreatetruecolor($w, $h);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $t = ($x / $w + $y / $h) / 2;
                $opacity = 0.5 - 0.35 * $t;
                $alpha = (int) round(127 * (1 - $opacity));
                imagesetpixel($layer, $x, $y, ($alpha << 24) | ($tint[0] << 16) | ($tint[1] << 8) | $tint[2]);
            }
        }

        return $this->upscale($layer, $width, $height, withAlpha: true);
    }

    private function upscale(GdImage $source, int $width, int $height, bool $withAlpha = false): GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        // Opaque PNGs embed much faster in DomPDF than ones with an alpha channel.
        if ($withAlpha) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $image;
    }

    private function png(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }
}
