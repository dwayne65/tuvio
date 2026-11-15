<?php

namespace App\Support;

use App\Models\Ticket;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Pre-rendered artwork for the PDF ticket. DomPDF cannot draw gradients, blend images
 * or follow SVG arcs, so every layer that needs them is rendered here (as data URIs)
 * from the category design in TicketDesign. Sizes are in PDF points.
 */
class TicketArtwork
{
    /** Pixels per PDF point, so rasters stay sharp when printed. */
    private const SCALE = 3;

    /** The photo panel sits behind vector text, so it needs less resolution than the metal and seal. */
    private const PANEL_SCALE = 2;

    /** Smooth gradients are computed at 1/DETAIL resolution and resampled up (keeps rendering fast). */
    private const DETAIL = 4;

    /** Where the header logo sits on the main panel, in points (the view lays the wordmark beside it). */
    public const LOGO_X = 26;

    public const LOGO_Y = 21;

    public const LOGO_HEIGHT = 12.5;

    /** @var array<string, string> */
    private array $cache = [];

    /** @var array<string, GdImage> */
    private array $tintedMarks = [];

    private ?GdImage $mark = null;

    /**
     * The main panel: the design's gradient with the event cover blended into its right
     * side, and a faint TUVIO watermark. Text is laid over the left side.
     *
     * @param  array<string, mixed>  $design
     */
    public function panel(Ticket $ticket, array $design, int $width, int $height): string
    {
        $event = $ticket->event;
        $key = "panel.{$design['key']}.{$event->id}.{$event->cover_image_path}.{$width}x{$height}";

        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        $w = $width * self::PANEL_SCALE;
        $h = $height * self::PANEL_SCALE;
        $base = TicketDesign::rgb($design['base']);
        $base2 = TicketDesign::rgb($design['base2']);
        $accent = TicketDesign::rgb($design['accent']);

        $image = $this->gradient($w, $h, $base, $base2, [
            [$accent, 0.0, 0.0, 0.55, $design['dark'] ? 0.16 : 0.06],
            [TicketDesign::rgb($design['accent2']), 1.0, 1.0, 0.6, $design['dark'] ? 0.12 : 0.1],
        ]);

        $cover = $this->loadCover($ticket->event->cover_image_path);

        if ($cover) {
            // Light designs wash the photo out so dark covers never fight the ink-coloured text.
            $design['dark']
                ? $this->blendCover($image, $cover, $base, (int) round($w * 0.46), tone: 28, strength: 82)
                : $this->blendCover($image, $cover, $base, (int) round($w * 0.52), tone: 58, strength: 50);
        }

        $this->watermark($image, $design, (int) round($w * 0.03), (int) round($h * 0.30), (int) round($h * 0.95));

        // The header logo is painted in here: a transparent PNG layer is slow for DomPDF.
        $this->paintMark($image, self::LOGO_X * self::PANEL_SCALE, self::LOGO_Y * self::PANEL_SCALE, (int) round(self::LOGO_HEIGHT * self::PANEL_SCALE));

        return $this->cache[$key] = $this->jpeg($image);
    }

    /**
     * The tear-off stub: a deep gradient lit by the accent.
     *
     * @param  array<string, mixed>  $design
     */
    public function stub(array $design, int $width, int $height): string
    {
        return $this->cache["stub.{$design['key']}.{$width}x{$height}"] ??= $this->jpeg(
            $this->gradient($width * self::SCALE, $height * self::SCALE, TicketDesign::rgb($design['stubFrom']), TicketDesign::rgb($design['stubTo']), [
                [TicketDesign::rgb($design['accent']), 1.0, 0.0, 0.8, 0.22],
                [TicketDesign::rgb($design['accent2']), 0.0, 1.0, 0.7, 0.12],
            ])
        );
    }

    /**
     * A brushed-metal strip in the design's three metal tones, for rules and badges.
     *
     * @param  array<string, mixed>  $design
     */
    public function metal(array $design, int $width, int $height): string
    {
        [$from, $shine, $to] = array_map(TicketDesign::rgb(...), $design['metal']);

        return $this->cache["metal.{$design['key']}.{$width}x{$height}"] ??= $this->png(
            $this->gradient($width * self::SCALE, $height * self::SCALE, $from, $to, [[$shine, 0.42, 0.5, 0.32, 1.0], [$shine, 0.85, 0.5, 0.12, 0.6]], horizontal: true)
        );
    }

    /**
     * A round holographic seal for premium tiers: an iridescent ring of the metal tones
     * with a prismatic shimmer, around a dark core (the glyph is drawn over it as text).
     *
     * @param  array<string, mixed>  $design
     */
    public function seal(array $design, int $size): string
    {
        return $this->cache["seal.{$design['key']}.{$size}"] ??= $this->png((function () use ($design, $size) {
            $px = $size * self::SCALE * 2;
            $image = imagecreatetruecolor($px, $px);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));

            $metal = array_map(TicketDesign::rgb(...), $design['metal']);
            $prism = [[255, 160, 220], [170, 200, 255], [160, 255, 225], [255, 236, 170]];
            $core = TicketDesign::rgb($design['stubTo']);
            $centre = $px / 2;

            for ($y = 0; $y < $px; $y++) {
                for ($x = 0; $x < $px; $x++) {
                    $dx = $x - $centre;
                    $dy = $y - $centre;
                    $r = sqrt($dx * $dx + $dy * $dy) / $centre;

                    if ($r > 1) {
                        continue;
                    }

                    $angle = (atan2($dy, $dx) + M_PI) / (2 * M_PI);
                    $t = fmod($angle * 3, 1.0);
                    $color = $t < 0.5 ? $this->mix($metal[0], $metal[1], $t * 2) : $this->mix($metal[1], $metal[2], ($t - 0.5) * 2);
                    $color = $this->mix($color, $prism[(int) floor($angle * 4) % 4], 0.22);

                    if ($r < 0.74) {
                        $color = $this->mix($core, $color, max(0, ($r - 0.66) / 0.08) * 0.6);
                    }

                    // Soft anti-aliased outer edge.
                    $alpha = $r > 0.97 ? (int) round(127 * ($r - 0.97) / 0.03) : 0;
                    imagesetpixel($image, $x, $y, ($alpha << 24) | ((int) $color[0] << 16) | ((int) $color[1] << 8) | (int) $color[2]);
                }
            }

            $out = imagecreatetruecolor($size * self::SCALE, $size * self::SCALE);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagecopyresampled($out, $image, 0, 0, 0, 0, $size * self::SCALE, $size * self::SCALE, $px, $px);

            return $out;
        })());
    }

    /**
     * The category's signature pattern over the main panel, drawn as vector lines.
     *
     * @param  array<string, mixed>  $design
     */
    public function pattern(array $design, int $width, int $height): string
    {
        $pattern = match ($design['pattern']) {
            'deco' => $this->decoPattern($design, $width, $height),
            'guilloche' => $this->guillochePattern($design, $width, $height),
            'pinstripe' => $this->pinstripePattern($design, $width, $height),
            default => $this->gridPattern($design, $width, $height),
        };

        // Hairlines separating the event details, the ticket holder row and the footer.
        $rules = '';
        foreach ([180, 225] as $y) {
            $rules .= "<line x1=\"26\" y1=\"{$y}\" x2=\"".($width - 20)."\" y2=\"{$y}\" stroke=\"{$design['rule']}\" stroke-opacity=\"".($design['dark'] ? 0.16 : 0.2).'" stroke-width="0.6"/>';
        }

        return $this->cache["pattern.{$design['key']}.{$width}x{$height}"] ??= $this->svg($width, $height, $pattern.$rules);
    }

    /**
     * Frame, perforation and cut-outs over the whole ticket: the category border, the
     * dashed tear line with its semicircular notches, and rounded corners in the page colour.
     *
     * @param  array<string, mixed>  $design
     */
    public function cutouts(array $design, int $width, int $height, int $tearAt, string $page = '#FFFFFF'): string
    {
        return $this->cache["cutouts.{$design['key']}.{$width}x{$height}.{$tearAt}.{$page}"] ??= $this->svg($width, $height, (function () use ($design, $width, $height, $tearAt, $page) {
            $rule = $design['rule'];
            $parts = [];

            // Category frame: a fine inset line, doubled for VIP, with corner ornaments for VVIP.
            $frameOpacity = $design['dark'] ? ($design['key'] === 'ordinary' ? 0.14 : 0.45) : 0.28;
            $parts[] = $this->insetFrame(7, $width, $height, $rule, $frameOpacity);

            if ($design['key'] === 'vip') {
                $parts[] = $this->insetFrame(10, $width, $height, $rule, 0.22);
            }

            if ($design['key'] === 'vvip') {
                foreach ([[14, 14, 1, 1], [$width - 14, 14, -1, 1], [14, $height - 14, 1, -1], [$width - 14, $height - 14, -1, -1]] as [$x, $y, $sx, $sy]) {
                    $parts[] = "<polyline points=\"{$x},".($y + 14 * $sy)." {$x},{$y} ".($x + 14 * $sx).",{$y}\" fill=\"none\" stroke=\"{$design['accent2']}\" stroke-width=\"1\" stroke-opacity=\"0.9\"/>";
                    $parts[] = '<circle cx="'.($x + 4 * $sx).'" cy="'.($y + 4 * $sy)."\" r=\"1.2\" fill=\"{$design['accent2']}\"/>";
                }
            }

            // Perforation, broken where the notches are.
            $parts[] = "<line x1=\"{$tearAt}\" y1=\"16\" x2=\"{$tearAt}\" y2=\"".($height - 16)."\" stroke=\"{$rule}\" stroke-opacity=\"0.5\" stroke-width=\"1.1\" stroke-dasharray=\"1.5,4\" stroke-linecap=\"round\"/>";
            $parts[] = "<circle cx=\"{$tearAt}\" cy=\"0\" r=\"10\" fill=\"{$page}\"/><circle cx=\"{$tearAt}\" cy=\"{$height}\" r=\"10\" fill=\"{$page}\"/>";

            // Rounded outer corners (DomPDF has no clip paths, so the page colour covers them).
            $r = 16;
            foreach ([[0, 0, 1, 1], [$width, 0, -1, 1], [0, $height, 1, -1], [$width, $height, -1, -1]] as [$x, $y, $sx, $sy]) {
                $points = [$x.','.$y];
                foreach (range(0, 12) as $step) {
                    $a = $step / 12 * M_PI_2;
                    $points[] = round($x + $sx * ($r - $r * cos($a)), 2).','.round($y + $sy * ($r - $r * sin($a)), 2);
                }
                $points[] = ($x + $sx * $r).','.$y;
                $parts[] = '<polygon points="'.implode(' ', $points)."\" fill=\"{$page}\"/>";
            }

            return implode('', $parts);
        })());
    }

    private function insetFrame(int $inset, int $width, int $height, string $color, float $opacity): string
    {
        $r = max(4, 16 - $inset);
        $w = $width - 2 * $inset;
        $h = $height - 2 * $inset;

        return "<rect x=\"{$inset}\" y=\"{$inset}\" width=\"{$w}\" height=\"{$h}\" rx=\"{$r}\" ry=\"{$r}\" fill=\"none\" stroke=\"{$color}\" stroke-opacity=\"{$opacity}\" stroke-width=\"0.7\"/>";
    }

    /** Ordinary: a precise dot grid fading out, with a few electric diagonal hairlines. */
    private function gridPattern(array $design, int $width, int $height): string
    {
        $parts = [];

        for ($y = 14; $y < $height; $y += 11) {
            for ($x = 14; $x < $width * 0.62; $x += 11) {
                $fade = 1 - $x / ($width * 0.62);
                $opacity = round(0.16 * $fade, 3);
                if ($opacity > 0.02) {
                    $parts[] = "<circle cx=\"{$x}\" cy=\"{$y}\" r=\"0.6\" fill=\"#FFFFFF\" fill-opacity=\"{$opacity}\"/>";
                }
            }
        }

        foreach ([0, 9, 18] as $i => $offset) {
            $x = $width * 0.5 + $offset;
            $parts[] = "<line x1=\"{$x}\" y1=\"0\" x2=\"".($x - 70)."\" y2=\"{$height}\" stroke=\"{$design['accent']}\" stroke-opacity=\"".(0.5 - $i * 0.15).'" stroke-width="0.7"/>';
        }

        return implode('', $parts);
    }

    /** VIP: an art-deco sunburst rising from the lower left, with stepped arches. */
    private function decoPattern(array $design, int $width, int $height): string
    {
        $parts = [];
        $cx = 0;
        $cy = $height;

        for ($i = 0; $i <= 26; $i++) {
            $a = $i / 26 * M_PI_2;
            $x2 = round($cx + cos($a) * $width * 0.75, 1);
            $y2 = round($cy - sin($a) * $width * 0.75, 1);
            $parts[] = "<line x1=\"{$cx}\" y1=\"{$cy}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"{$design['accent']}\" stroke-opacity=\"0.07\" stroke-width=\"0.6\"/>";
        }

        foreach ([60, 66, 120, 126, 180] as $i => $radius) {
            $points = collect(range(0, 30))->map(fn (int $s) => round($cx + $radius * cos($s / 30 * M_PI_2), 1).','.round($cy - $radius * sin($s / 30 * M_PI_2), 1))->implode(' ');
            $parts[] = "<polyline points=\"{$points}\" fill=\"none\" stroke=\"{$design['accent']}\" stroke-opacity=\"".($i % 2 ? 0.16 : 0.3).'" stroke-width="0.7"/>';
        }

        return implode('', $parts);
    }

    /** VVIP: a banknote-style guilloché rosette in platinum, the most intricate pattern. */
    private function guillochePattern(array $design, int $width, int $height): string
    {
        $parts = [];
        $cx = $width * 0.2;
        $cy = $height * 0.82;

        foreach ([[78, 22, 9], [64, 18, 11], [50, 14, 13]] as $i => [$big, $small, $petals]) {
            $points = [];
            for ($s = 0; $s <= 360; $s++) {
                $t = $s / 360 * 2 * M_PI;
                $r = $big + $small * sin($petals * $t);
                $points[] = round($cx + $r * cos($t), 1).','.round($cy + $r * sin($t) * 0.82, 1);
            }
            $parts[] = '<polyline points="'.implode(' ', $points).'" fill="none" stroke="'.($i === 1 ? $design['accent2'] : $design['accent']).'" stroke-opacity="'.($i === 1 ? 0.22 : 0.14).'" stroke-width="0.5"/>';
        }

        // Fine engraved waves across the top.
        foreach (range(0, 5) as $row) {
            $points = [];
            for ($x = 0; $x <= $width; $x += 6) {
                $points[] = $x.','.round(16 + $row * 4 + 2.2 * sin($x / 14 + $row), 1);
            }
            $parts[] = '<polyline points="'.implode(' ', $points)."\" fill=\"none\" stroke=\"{$design['accent']}\" stroke-opacity=\"0.06\" stroke-width=\"0.4\"/>";
        }

        return implode('', $parts);
    }

    /** Guest: quiet concentric arcs from the top right and fine pinstripes, in navy on ivory. */
    private function pinstripePattern(array $design, int $width, int $height): string
    {
        $parts = [];

        foreach (range(1, 9) as $i) {
            $radius = 26 * $i;
            $points = collect(range(0, 30))->map(fn (int $s) => round($width * 0.55 - $radius * cos($s / 30 * M_PI_2), 1).','.round($radius * sin($s / 30 * M_PI_2), 1))->implode(' ');
            $parts[] = "<polyline points=\"{$points}\" fill=\"none\" stroke=\"{$design['accent']}\" stroke-opacity=\"0.07\" stroke-width=\"0.6\"/>";
        }

        for ($x = 6; $x < $width; $x += 5) {
            $parts[] = "<line x1=\"{$x}\" y1=\"".($height - 9)."\" x2=\"{$x}\" y2=\"".($height - 5)."\" stroke=\"{$design['accent']}\" stroke-opacity=\"0.12\" stroke-width=\"0.4\"/>";
        }

        return implode('', $parts);
    }

    private function loadCover(?string $path): ?GdImage
    {
        // Remote covers are skipped: the PDF never fetches external URLs.
        if (! $path || str_starts_with($path, 'http')) {
            return null;
        }

        $bytes = Storage::disk('public')->get($path);
        $image = $bytes ? @imagecreatefromstring($bytes) : false;

        return $image ?: null;
    }

    /**
     * Fill the right of the panel with the cover (cropped to fit), toned towards the
     * base colour and fading into the panel from $fromX so the text side stays clean.
     * Uses GD's native merges column by column, which is far faster than per-pixel work.
     *
     * @param  array{int,int,int}  $base
     */
    private function blendCover(GdImage $panel, GdImage $cover, array $base, int $fromX, int $tone, int $strength): void
    {
        $w = imagesx($panel) - $fromX;
        $h = imagesy($panel);
        $photo = imagecreatetruecolor($w, $h);
        $scale = max($w / imagesx($cover), $h / imagesy($cover));
        $srcW = (int) round($w / $scale);
        $srcH = (int) round($h / $scale);
        imagecopyresampled($photo, $cover, 0, 0, (int) ((imagesx($cover) - $srcW) / 2), (int) ((imagesy($cover) - $srcH) / 2), $w, $h, $srcW, $srcH);

        // Tone the photo towards the base colour so every cover sits in the palette.
        $wash = imagecreatetruecolor($w, $h);
        imagefill($wash, 0, 0, imagecolorallocate($wash, ...$base));
        imagecopymerge($photo, $wash, 0, 0, 0, 0, $w, $h, $tone);

        $fadeWidth = max(1, (int) round($w * 0.55));

        for ($x = 0; $x < $w; $x++) {
            $edge = min(1, $x / $fadeWidth);
            $percent = (int) round($strength * $edge * $edge * (3 - 2 * $edge));

            if ($percent > 0) {
                imagecopymerge($panel, $photo, $fromX + $x, 0, $x, 0, 1, $h, $percent);
            }
        }
    }

    /**
     * A large, faint TUVIO mark behind the details: the mark is recoloured (white on dark
     * designs, ink on light ones), laid on a copy of the area, and merged back faintly.
     *
     * @param  array<string, mixed>  $design
     */
    private function watermark(GdImage $image, array $design, int $x, int $y, int $size): void
    {
        $mark = $this->brandMark();

        if (! $mark) {
            return;
        }

        $height = min($size, imagesy($image) - $y);
        $width = min((int) round(imagesx($mark) * $size / imagesy($mark)), imagesx($image) - $x);
        $scaled = $this->tintedMarks["{$design['key']}.{$size}"] ??= (function () use ($mark, $design, $size, $width, $height) {
            $scaled = imagecreatetruecolor($width, $height);
            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);
            imagecopyresampled($scaled, $mark, 0, 0, 0, 0, (int) round(imagesx($mark) * $size / imagesy($mark)), $size, imagesx($mark), imagesy($mark));

            [$r, $g, $b] = $design['dark'] ? [255, 255, 255] : TicketDesign::rgb($design['ink']);
            imagefilter($scaled, IMG_FILTER_GRAYSCALE);
            imagefilter($scaled, IMG_FILTER_BRIGHTNESS, -255);
            imagefilter($scaled, IMG_FILTER_COLORIZE, $r, $g, $b);

            return $scaled;
        })();

        $area = imagecreatetruecolor($width, $height);
        imagecopy($area, $image, 0, 0, $x, $y, $width, $height);
        imagealphablending($area, true);
        imagecopy($area, $scaled, 0, 0, 0, 0, $width, $height);
        imagecopymerge($image, $area, $x, $y, 0, 0, $width, $height, 4);
    }

    /** The TUVIO mark flattened onto white, for the print sheet header. */
    public function markOnWhite(int $height): string
    {
        return $this->cache["mark-white.{$height}"] ??= (function () use ($height) {
            $px = $height * self::SCALE;
            $mark = $this->brandMark();
            $width = $mark ? (int) round(imagesx($mark) * $px / imagesy($mark)) : $px;
            $image = imagecreatetruecolor($width, $px);
            imagefill($image, 0, 0, 0xFFFFFF);
            $this->paintMark($image, 0, 0, $px);

            return $this->png($image);
        })();
    }

    /** Paint the full-colour TUVIO mark onto an opaque image at the given pixel box. */
    private function paintMark(GdImage $image, int $x, int $y, int $height): void
    {
        $mark = $this->brandMark();

        if (! $mark) {
            return;
        }

        imagealphablending($image, true);
        imagecopyresampled($image, $mark, $x, $y, 0, 0, (int) round(imagesx($mark) * $height / imagesy($mark)), $height, imagesx($mark), imagesy($mark));
    }

    private function brandMark(): ?GdImage
    {
        return $this->mark ??= (@imagecreatefrompng(public_path('brand/tuvio-mark.png')) ?: null);
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

    private function upscale(GdImage $source, int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);
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
        imagepng($image, null, 6);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    private function jpeg(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, 90);

        return 'data:image/jpeg;base64,'.base64_encode((string) ob_get_clean());
    }
}
