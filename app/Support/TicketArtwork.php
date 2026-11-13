<?php

namespace App\Support;

use App\Models\Ticket;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Presentation-only artwork for the PDF ticket (tier palette, gradients, the poster
 * photo and the vector decoration). DomPDF cannot draw CSS gradients, blend modes
 * or crop images, so those layers are pre-rendered here as data URIs for the view.
 */
class TicketArtwork
{
    /** Pixels per PDF point, so rasters stay sharp when printed. */
    private const SCALE = 2;

    /** Smooth gradients are computed at 1/DETAIL resolution and resampled up (keeps rendering fast). */
    private const DETAIL = 4;

    /**
     * Every tier is a dark "poster" ticket; the tier sets the colours.
     *
     * @var array<string, array{label: string, glyph: string, accent: string, accent2: string, title: string, ink: string, muted: string, tierText: string, stubInk: string, stubMuted: string, base: array{int,int,int}, glow: array{int,int,int}, glow2: array{int,int,int}, stubFrom: array{int,int,int}, stubTo: array{int,int,int}, foil: array{array{int,int,int}, array{int,int,int}, array{int,int,int}}}>
     */
    private const TIERS = [
        'standard' => [
            'label' => 'STANDARD', 'glyph' => '✦',
            'accent' => '#8B6CFF', 'accent2' => '#5EE7FF', 'title' => '#B9A8FF',
            'ink' => '#FFFFFF', 'muted' => '#B4B1D1', 'tierText' => '#FFFFFF', 'stubInk' => '#FFFFFF', 'stubMuted' => '#D9D1FF',
            'base' => [13, 10, 34], 'glow' => [118, 85, 247], 'glow2' => [94, 231, 255],
            'stubFrom' => [124, 92, 255], 'stubTo' => [42, 22, 128],
            'foil' => [[94, 231, 255], [139, 108, 255], [255, 122, 217]],
        ],
        'vip' => [
            'label' => 'VIP', 'glyph' => '★',
            'accent' => '#E9C46A', 'accent2' => '#FFF1C9', 'title' => '#F2CF7A',
            'ink' => '#FFFFFF', 'muted' => '#C9BC9C', 'tierText' => '#F2CF7A', 'stubInk' => '#FBF1D6', 'stubMuted' => '#CDB98C',
            'base' => [12, 10, 6], 'glow' => [201, 151, 59], 'glow2' => [255, 223, 150],
            'stubFrom' => [52, 40, 18], 'stubTo' => [9, 8, 6],
            'foil' => [[140, 98, 30], [246, 216, 140], [176, 128, 46]],
        ],
        'vvip' => [
            'label' => 'VVIP', 'glyph' => '♛',
            'accent' => '#FF7AD9', 'accent2' => '#9D8CFF', 'title' => '#FF9BE3',
            'ink' => '#FFFFFF', 'muted' => '#B8B5CC', 'tierText' => '#E6DEFF', 'stubInk' => '#FFFFFF', 'stubMuted' => '#C7C0E6',
            'base' => [9, 8, 16], 'glow' => [255, 122, 217], 'glow2' => [157, 140, 255],
            'stubFrom' => [26, 20, 52], 'stubTo' => [8, 7, 14],
            'foil' => [[255, 122, 217], [157, 140, 255], [94, 231, 255]],
        ],
    ];

    /** @var array<string, string> */
    private array $cache = [];

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

        return ['key' => $key, ...self::TIERS[$key], 'label' => $label, 'baseHex' => $this->hex(self::TIERS[$key]['base'])];
    }

    /** Tear-off stub background: the tier gradient lit by two soft glows. */
    public function stub(array $tier, int $width, int $height): string
    {
        return $this->cache["stub.{$tier['key']}.{$width}x{$height}"] ??= $this->png(
            $this->gradient($width * self::SCALE, $height * self::SCALE, $tier['stubFrom'], $tier['stubTo'], [
                [$tier['glow2'], 1.0, 0.0, 0.85, 0.45],
                [$tier['glow'], 0.0, 1.0, 0.7, 0.35],
            ])
        );
    }

    /** Thin metallic foil strip in the tier's colours. */
    public function foil(array $tier, int $width, int $height): string
    {
        [$from, $mid, $to] = $tier['foil'];

        return $this->cache["foil.{$tier['key']}.{$width}x{$height}"] ??= $this->png(
            $this->gradient($width * self::SCALE, $height * self::SCALE, $from, $to, [[$mid, 0.5, 0.5, 0.4, 1.0]], horizontal: true)
        );
    }

    /**
     * The main panel: the event cover filling the panel, darkened from the left so the
     * text stays readable and lit with the tier's glows. Events without a cover get an
     * abstract tier gradient instead.
     */
    public function poster(Ticket $ticket, array $tier, int $width, int $height): string
    {
        $w = $width * self::SCALE;
        $h = $height * self::SCALE;
        $cover = $this->loadCover($ticket->event->cover_image_path);

        if (! $cover) {
            return $this->cache["poster.{$tier['key']}.{$width}x{$height}"] ??= $this->jpeg($this->withShade(
                $this->gradient($w, $h, $this->mix($tier['base'], $tier['glow'], 0.55), $tier['base'], [
                    [$tier['glow2'], 1.0, 0.0, 0.9, 0.55],
                    [$tier['glow'], 0.75, 1.0, 0.6, 0.5],
                ]),
                $tier,
            ));
        }

        $photo = imagecreatetruecolor($w, $h);
        $scale = max($w / imagesx($cover), $h / imagesy($cover));
        $srcW = (int) round($w / $scale);
        $srcH = (int) round($h / $scale);
        imagecopyresampled($photo, $cover, 0, 0, (int) ((imagesx($cover) - $srcW) / 2), (int) ((imagesy($cover) - $srcH) / 2), $w, $h, $srcW, $srcH);

        return $this->jpeg($this->withShade($photo, $tier));
    }

    /**
     * Vector decoration over the main panel: halftone dots, "sound wave" rings and the
     * info bar rules. Drawn in PDF points on a 456 × 261 canvas.
     */
    public function decoration(array $tier): string
    {
        return $this->cache["decoration.{$tier['key']}"] ??= $this->svg(456, 261, (function () use ($tier) {
            $parts = [];

            // Halftone dots fading out towards the bottom-left.
            for ($row = 0; $row < 8; $row++) {
                for ($col = 0; $col < 15; $col++) {
                    $x = 318 + $col * 9;
                    $y = 16 + $row * 9;
                    $r = round(1.9 * (1 - $row / 9) * ($col / 15 + 0.25), 2);
                    if ($r > 0.3) {
                        $parts[] = "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"{$r}\" fill=\"{$tier['accent']}\" fill-opacity=\"0.55\"/>";
                    }
                }
            }

            // Sound-wave rings rising from the bottom-right corner, kept inside the panel.
            foreach ([70, 100, 130, 160, 190, 220] as $i => $radius) {
                $opacity = round(0.42 - $i * 0.06, 2);
                // DomPDF's SVG renderer skips arc commands, so the quarter circle is a polyline.
                $points = collect(range(0, 24))->map(fn (int $step) => round(456 - $radius * cos($step / 24 * M_PI_2), 1).','.round(261 - $radius * sin($step / 24 * M_PI_2), 1))->implode(' ');
                $parts[] = "<polyline points=\"{$points}\" fill=\"none\" stroke=\"{$tier['accent2']}\" stroke-width=\"0.9\" stroke-opacity=\"{$opacity}\"/>";
            }

            // Accent slash and the info bar rules.
            $parts[] = "<polygon points=\"26,180 70,180 66,183 22,183\" fill=\"{$tier['accent']}\"/>";
            $parts[] = '<line x1="26" y1="190" x2="430" y2="190" stroke="#FFFFFF" stroke-opacity="0.18" stroke-width="0.8"/>';
            $parts[] = '<line x1="146" y1="198" x2="146" y2="222" stroke="#FFFFFF" stroke-opacity="0.18" stroke-width="0.8"/>';
            $parts[] = '<line x1="238" y1="198" x2="238" y2="222" stroke="#FFFFFF" stroke-opacity="0.18" stroke-width="0.8"/>';

            return implode('', $parts);
        })());
    }

    /** Cut-outs over the whole ticket: perforation, notches and rounded corners (page white). */
    public function cutouts(array $tier): string
    {
        return $this->cache["cutouts.{$tier['key']}"] ??= $this->svg(624, 261,
            '<line x1="456" y1="14" x2="456" y2="247" stroke="#FFFFFF" stroke-opacity="0.6" stroke-width="1.4" stroke-dasharray="4,4"/>'
            .'<circle cx="456" cy="0" r="10" fill="#ffffff"/><circle cx="456" cy="261" r="10" fill="#ffffff"/>'
            .'<path d="M0,0 L16,0 A16,16 0 0,0 0,16 Z" fill="#ffffff"/>'
            .'<path d="M624,0 L608,0 A16,16 0 0,1 624,16 Z" fill="#ffffff"/>'
            .'<path d="M0,261 L16,261 A16,16 0 0,1 0,245 Z" fill="#ffffff"/>'
            .'<path d="M624,261 L608,261 A16,16 0 0,0 624,245 Z" fill="#ffffff"/>'
        );
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
     * Lay the readability shade over an image: near-solid tier base on the left fading
     * out to the right, a darker band along the bottom for the info bar, and the
     * tier's glows bleeding in from the corners.
     */
    private function withShade(GdImage $image, array $tier): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $w = max(2, intdiv($width, self::DETAIL));
        $h = max(2, intdiv($height, self::DETAIL));
        $layer = imagecreatetruecolor($w, $h);
        imagealphablending($layer, false);
        imagesavealpha($layer, true);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $u = $x / $w;
                $v = $y / $h;
                $opacity = $u < 0.3 ? 0.94 : 0.94 - ($u - 0.3) / 0.7 * 0.66;
                $opacity = max($opacity, min(0.9, max(0, ($v - 0.6) / 0.4) * 0.9));

                $glowA = 0.45 * max(0, 1 - sqrt(($u - 0.0) ** 2 + ($v - 1.0) ** 2) / 0.75) ** 2;
                $glowB = 0.35 * max(0, 1 - sqrt(($u - 0.95) ** 2 + ($v - 0.0) ** 2) / 0.6) ** 2;
                $color = $this->mix($this->mix($tier['base'], $tier['glow'], $glowA), $tier['glow2'], $glowB);

                $alpha = (int) round(127 * (1 - min(1, $opacity + $glowA * 0.3)));
                imagesetpixel($layer, $x, $y, ($alpha << 24) | ((int) $color[0] << 16) | ((int) $color[1] << 8) | (int) $color[2]);
            }
        }

        imagealphablending($image, true);
        imagecopy($image, $this->upscale($layer, $width, $height, withAlpha: true), 0, 0, 0, 0, $width, $height);

        return $image;
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
                $c = $this->mix($from, $to, $horizontal ? $x / $w : ($x / $w + $y / $h) / 2);

                foreach ($glows as [$glow, $gx, $gy, $radius, $strength]) {
                    $d = $horizontal
                        ? abs($x - $gx * $w) / ($radius * $w)
                        : sqrt(($x - $gx * $w) ** 2 + ($y - $gy * $h) ** 2) / ($radius * $span);
                    $c = $this->mix($c, $glow, $strength * max(0, 1 - $d) ** 2);
                }

                imagesetpixel($image, $x, $y, ((int) $c[0] << 16) | ((int) $c[1] << 8) | (int) $c[2]);
            }
        }

        return $this->upscale($image, $width, $height);
    }

    /**
     * @param  array{int|float,int|float,int|float}  $from
     * @param  array{int|float,int|float,int|float}  $to
     * @return array{float,float,float}
     */
    private function mix(array $from, array $to, float $t): array
    {
        return [$from[0] + ($to[0] - $from[0]) * $t, $from[1] + ($to[1] - $from[1]) * $t, $from[2] + ($to[2] - $from[2]) * $t];
    }

    /** @param array{int,int,int} $rgb */
    private function hex(array $rgb): string
    {
        return sprintf('#%02X%02X%02X', ...$rgb);
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

    private function svg(int $width, int $height, string $content): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode(
            "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\">{$content}</svg>"
        );
    }

    private function png(GdImage $image): string
    {
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private function jpeg(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 88);

        return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
    }
}
