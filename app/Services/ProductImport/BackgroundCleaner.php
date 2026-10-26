<?php

namespace App\Services\ProductImport;

/**
 * "Quick clean": swaps a plain studio background for Shaotal beige (#F5EDE3) and centres the product on a square canvas.
 * Only background pixels are replaced (found by flood-filling inward from the photo's edge), so the product itself is never
 * redrawn. Photos without a plain background are refused rather than guessed at.
 */
class BackgroundCleaner
{
    public const CANVAS = 1600;

    private const BG = 0xF5EDE3;

    /**
     * Largest per-channel difference from the typical edge colour that still counts as backdrop. Kept tight on purpose:
     * following soft gradients was tried and it leaked into skin and fabric, which would damage the product.
     */
    private const REACH = 30;

    /** Share of the photo's edge the fill must reach, otherwise the backdrop is not plain. */
    private const MIN_EDGE_COVERAGE = 0.88;

    private const MIN_BACKGROUND = 0.08;

    private const PADDING = 0.06;

    /** @return string JPEG bytes */
    public function clean(string $bytes): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new ImportException('The server has no image library (GD).', false);
        }
        @ini_set('memory_limit', '512M');
        @set_time_limit(120);

        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            throw new ImportException('The source image could not be read.', false);
        }

        $scale = min(1, self::CANVAS / max(imagesx($src), imagesy($src)));
        $w = max(1, (int) round(imagesx($src) * $scale));
        $h = max(1, (int) round(imagesy($src) * $scale));
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, 0xFFFFFF);
        imagealphablending($img, true);
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));
        imagedestroy($src);

        $bg = $this->borderColor($img, $w, $h);
        $mask = $this->backgroundMask($img, $w, $h, $bg);
        if ($this->edgeCoverage($mask, $w, $h) < self::MIN_EDGE_COVERAGE) {
            throw new ImportException('This photo has no plain background to replace. Use AI generation or the original.', false);
        }

        $bgCount = substr_count($mask, "\1");
        if ($bgCount / ($w * $h) < self::MIN_BACKGROUND) {
            throw new ImportException('This photo has no plain background to replace. Use AI generation or the original.', false);
        }

        [$minX, $minY, $maxX, $maxY] = $this->paint($img, $w, $h, $mask);
        if ($maxX < $minX || ($maxX - $minX + 1) * ($maxY - $minY + 1) < 0.02 * $w * $h) {
            throw new ImportException('No product was found on the plain background.', false);
        }

        return $this->compose($img, $minX, $minY, $maxX, $maxY);
    }

    /** Median colour along the photo's edge as [r, g, b]. */
    private function borderColor($img, int $w, int $h): array
    {
        $samples = [];
        $step = max(1, (int) floor(max($w, $h) / 400));
        for ($x = 0; $x < $w; $x += $step) {
            $samples[] = imagecolorat($img, $x, 0);
            $samples[] = imagecolorat($img, $x, $h - 1);
        }
        for ($y = 0; $y < $h; $y += $step) {
            $samples[] = imagecolorat($img, 0, $y);
            $samples[] = imagecolorat($img, $w - 1, $y);
        }
        $median = fn (array $v) => (function () use ($v) {
            sort($v);

            return $v[intdiv(count($v), 2)];
        })();
        $bg = [
            $median(array_map(fn ($c) => ($c >> 16) & 255, $samples)),
            $median(array_map(fn ($c) => ($c >> 8) & 255, $samples)),
            $median(array_map(fn ($c) => $c & 255, $samples)),
        ];

        return $bg;
    }

    private function near(int $c, array $bg): bool
    {
        return abs((($c >> 16) & 255) - $bg[0]) <= self::REACH
            && abs((($c >> 8) & 255) - $bg[1]) <= self::REACH
            && abs(($c & 255) - $bg[2]) <= self::REACH;
    }

    private function edgeCoverage(string $mask, int $w, int $h): float
    {
        $hit = 0;
        for ($x = 0; $x < $w; $x++) {
            $hit += ($mask[$x] === "\1" ? 1 : 0) + ($mask[($h - 1) * $w + $x] === "\1" ? 1 : 0);
        }
        for ($y = 0; $y < $h; $y++) {
            $hit += ($mask[$y * $w] === "\1" ? 1 : 0) + ($mask[$y * $w + $w - 1] === "\1" ? 1 : 0);
        }

        return $hit / (2 * ($w + $h));
    }

    /** One byte per pixel: "\1" = backdrop connected to the photo edge, "\0" = product. */
    private function backgroundMask($img, int $w, int $h, array $bg): string
    {
        $mask = str_repeat("\0", $w * $h);
        $stack = [];
        $seed = function (int $x, int $y) use (&$mask, &$stack, $img, $w, $bg) {
            $i = $y * $w + $x;
            if ($mask[$i] === "\0" && $this->near(imagecolorat($img, $x, $y), $bg)) {
                $mask[$i] = "\1";
                $stack[] = $i;
            }
        };
        for ($x = 0; $x < $w; $x++) {
            $seed($x, 0);
            $seed($x, $h - 1);
        }
        for ($y = 0; $y < $h; $y++) {
            $seed(0, $y);
            $seed($w - 1, $y);
        }
        $grow = function (int $x, int $y) use (&$mask, &$stack, $img, $w, $bg) {
            $i = $y * $w + $x;
            if ($mask[$i] === "\0") {
                $c = imagecolorat($img, $x, $y);
                if ($this->near($c, $bg)) {
                    $mask[$i] = "\1";
                    $stack[] = $i;
                }
            }
        };
        while ($stack) {
            $i = array_pop($stack);
            $x = $i % $w;
            $y = intdiv($i, $w);
            if ($x > 0) {
                $grow($x - 1, $y);
            }
            if ($x < $w - 1) {
                $grow($x + 1, $y);
            }
            if ($y > 0) {
                $grow($x, $y - 1);
            }
            if ($y < $h - 1) {
                $grow($x, $y + 1);
            }
        }

        return $mask;
    }

    /** Paints the beige over the background, softens the product's edge, returns the product's bounding box. */
    private function paint($img, int $w, int $h, string $mask): array
    {
        [$minX, $minY, $maxX, $maxY] = [$w, $h, -1, -1];
        $soft = (self::BG & 0xFEFEFE) >> 1;
        for ($y = 0; $y < $h; $y++) {
            $row = $y * $w;
            for ($x = 0; $x < $w; $x++) {
                if ($mask[$row + $x] === "\1") {
                    imagesetpixel($img, $x, $y, self::BG);

                    continue;
                }
                if ($x < $minX) {
                    $minX = $x;
                }
                if ($x > $maxX) {
                    $maxX = $x;
                }
                if ($y < $minY) {
                    $minY = $y;
                }
                if ($y > $maxY) {
                    $maxY = $y;
                }
                $edge = ($x > 0 && $mask[$row + $x - 1] === "\1") || ($x < $w - 1 && $mask[$row + $x + 1] === "\1")
                    || ($y > 0 && $mask[$row - $w + $x] === "\1") || ($y < $h - 1 && $mask[$row + $w + $x] === "\1");
                if ($edge) {
                    // Half-way blend removes the pale fringe left by the old background.
                    $c = imagecolorat($img, $x, $y);
                    imagesetpixel($img, $x, $y, ((($c & 0xFEFEFE) >> 1) + $soft) & 0xFFFFFF);
                }
            }
        }

        return [$minX, $minY, $maxX, $maxY];
    }

    private function compose($img, int $minX, int $minY, int $maxX, int $maxY): string
    {
        $bw = $maxX - $minX + 1;
        $bh = $maxY - $minY + 1;
        $avail = (int) round(self::CANVAS * (1 - 2 * self::PADDING));
        $scale = min($avail / $bw, $avail / $bh);
        $tw = max(1, (int) round($bw * $scale));
        $th = max(1, (int) round($bh * $scale));

        $canvas = imagecreatetruecolor(self::CANVAS, self::CANVAS);
        imagefill($canvas, 0, 0, self::BG);
        imagecopyresampled($canvas, $img, intdiv(self::CANVAS - $tw, 2), intdiv(self::CANVAS - $th, 2), $minX, $minY, $tw, $th, $bw, $bh);
        imagedestroy($img);

        ob_start();
        imagejpeg($canvas, null, 92);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }
}
