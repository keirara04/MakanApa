<?php

namespace App\Support;

use GdImage;

/**
 * Draws the 1200×630 link-preview card for a shared place (og:image). Chat apps cache previews
 * per URL for days, so a card only ever shows facts that don't change by the hour: never "open now".
 */
final class ShareCardImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    private const LEFT = 80;

    private const TEXT_WIDTH = 580;

    private const CREAM = [253, 246, 236];

    private const INK = [43, 28, 20];

    private const MUTED = [106, 93, 85];

    private const SAMBAL = [204, 56, 20];

    private const BLUSH = [255, 223, 214];

    /** GD needs freetype for TTF text; without it callers fall back to the static image. */
    public static function supported(): bool
    {
        return function_exists('imagettftext');
    }

    /**
     * Fredoka's latin subset has no CJK/Tamil glyphs. A name that needs them would render as
     * boxes, so those places keep the generic card instead.
     */
    public static function canRender(string $text): bool
    {
        return preg_match('/[^\x{0000}-\x{024F}\x{2000}-\x{206F}]/u', $text) !== 1;
    }

    /**
     * @param  array{kicker: string, title: string, subtitle: ?string, detail: ?string, pills: list<array{label: string, background: array{int, int, int}, text: array{int, int, int}}>, footer: ?string}  $card
     */
    public static function render(array $card): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefill($image, 0, 0, self::color($image, self::CREAM));
        imagefilledellipse($image, 960, 315, 600, 600, self::color($image, self::BLUSH));
        self::drawMascot($image);

        $y = 118;
        self::text($image, mb_strtoupper($card['kicker']), 26, self::bold(), self::SAMBAL, self::LEFT, $y);

        $y += 30;
        foreach (self::fitTitle($card['title']) as [$line, $size]) {
            $y += (int) round($size * 1.18);
            self::text($image, $line, $size, self::bold(), self::INK, self::LEFT, $y);
        }

        foreach (array_filter([$card['subtitle'], $card['detail']]) as $line) {
            $y += 54;
            self::text($image, self::ellipsize($line, 30, self::medium(), self::TEXT_WIDTH), 30, self::medium(), self::MUTED, self::LEFT, $y);
        }

        $x = self::LEFT;
        foreach ($card['pills'] as $pill) {
            $x += self::pill($image, $pill['label'], $x, 470, $pill['background'], $pill['text']) + 14;
        }

        $brandWidth = self::pill($image, 'MakanApa', self::LEFT, 548, self::INK, self::CREAM);
        if ($card['footer'] !== null) {
            self::text($image, $card['footer'], 24, self::medium(), self::MUTED, self::LEFT + $brandWidth + 22, 590);
        }

        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * Long names step the size down and wrap to two lines; whatever still doesn't fit is
     * ellipsized, so "KFC" and a 60-character warung name both land inside the text column.
     *
     * @return list<array{string, int}>
     */
    private static function fitTitle(string $title): array
    {
        foreach ([68, 60, 52] as $size) {
            $lines = self::wrap($title, $size);
            if (count($lines) <= 2) {
                return array_map(fn (string $line) => [$line, $size], $lines);
            }
        }

        $lines = self::wrap($title, 52);
        $rest = implode(' ', array_slice($lines, 1));

        return [[$lines[0], 52], [self::ellipsize($rest, 52, self::bold(), self::TEXT_WIDTH, force: true), 52]];
    }

    /** @return list<string> */
    private static function wrap(string $text, int $size): array
    {
        $lines = [];
        $line = '';
        foreach (preg_split('/\s+/u', trim($text)) as $word) {
            $candidate = $line === '' ? $word : "{$line} {$word}";
            if ($line !== '' && self::width($candidate, $size, self::bold()) > self::TEXT_WIDTH) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $candidate;
            }
        }
        $lines[] = $line;

        return array_map(fn (string $l) => self::ellipsize($l, $size, self::bold(), self::TEXT_WIDTH), $lines);
    }

    private static function ellipsize(string $text, int $size, string $font, int $maxWidth, bool $force = false): string
    {
        if (! $force && self::width($text, $size, $font) <= $maxWidth) {
            return $text;
        }
        while ($text !== '' && self::width($text.'…', $size, $font) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    /** @param  array{int, int, int}  $background  @param  array{int, int, int}  $foreground */
    private static function pill(GdImage $image, string $label, int $x, int $top, array $background, array $foreground): int
    {
        $size = 26;
        $height = 54;
        $width = self::width($label, $size, self::medium()) + 48;
        $fill = self::color($image, $background);
        $radius = intdiv($height, 2);

        imagefilledrectangle($image, $x + $radius, $top, $x + $width - $radius, $top + $height, $fill);
        imagefilledellipse($image, $x + $radius, $top + $radius, $height, $height, $fill);
        imagefilledellipse($image, $x + $width - $radius, $top + $radius, $height, $height, $fill);
        self::text($image, $label, $size, self::medium(), $foreground, $x + 24, $top + 37);

        return $width;
    }

    private static function drawMascot(GdImage $image): void
    {
        $mascot = imagecreatefrompng(resource_path('images/share-mascot.png'));
        imagealphablending($image, true);
        imagecopyresampled($image, $mascot, 700, 55, 0, 0, 520, 520, imagesx($mascot), imagesy($mascot));
    }

    /** @param  array{int, int, int}  $rgb */
    private static function text(GdImage $image, string $text, int $size, string $font, array $rgb, int $x, int $baseline): void
    {
        imagettftext($image, $size, 0, $x, $baseline, self::color($image, $rgb), $font, $text);
    }

    private static function width(string $text, int $size, string $font): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box[2] - $box[0];
    }

    /** @param  array{int, int, int}  $rgb */
    private static function color(GdImage $image, array $rgb): int
    {
        return imagecolorallocate($image, ...$rgb);
    }

    private static function bold(): string
    {
        return resource_path('fonts/fredoka-700.ttf');
    }

    private static function medium(): string
    {
        return resource_path('fonts/fredoka-500.ttf');
    }
}
