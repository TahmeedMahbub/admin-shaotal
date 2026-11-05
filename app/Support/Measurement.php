<?php

namespace App\Support;

/**
 * Size guide measurements: a single number ("38", "27.5") or a range ("71.1-76.2"), in a STORED unit ('in'|'cm').
 * The stored unit is a property of the saved data; the display unit is only a view. Conversions always start from the
 * stored original (never from an already converted value), 1 in = 2.54 cm, max 2 decimals.
 */
class Measurement
{
    public const CM_PER_IN = 2.54;

    private const NUM = '\d+(?:\.\d{1,2})?';

    /** Unit spellings -> 'in' | 'cm'; null when unknown. */
    public static function unit(?string $u): ?string
    {
        return match (strtolower(trim((string) $u))) {
            'in', 'inch', 'inches', '"' => 'in',
            'cm', 'centimeter', 'centimeters', 'centimetre', 'centimetres' => 'cm',
            default => null,
        };
    }

    /** @return array{0: float, 1: ?float}|null [min, max] (max null for a single value); null when invalid. */
    public static function parse(?string $raw): ?array
    {
        $raw = str_replace(["\u{2013}", "\u{2014}"], '-', trim((string) $raw));
        if (preg_match('/^('.self::NUM.')$/', $raw, $m)) {
            return [(float) $m[1], null];
        }
        if (preg_match('/^('.self::NUM.')\s*-\s*('.self::NUM.')$/', $raw, $m) && (float) $m[1] <= (float) $m[2]) {
            return [(float) $m[1], (float) $m[2]];
        }

        return null;
    }

    public static function isValid(?string $raw): bool
    {
        return self::parse($raw) !== null;
    }

    private static function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    /** Canonical text ("38.00" -> "38", "71.1 - 76.2" -> "71.1-76.2"); invalid input is returned trimmed, untouched. */
    public static function normalize(?string $raw): string
    {
        $p = self::parse($raw);

        return $p ? self::num($p[0]).($p[1] === null ? '' : '-'.self::num($p[1])) : trim((string) $raw);
    }

    /** Converts a stored value from one unit to another (each end of a range); same unit just normalises. */
    public static function convert(?string $raw, ?string $from, ?string $to): string
    {
        $p = self::parse($raw);
        if (! $p || ! $from || ! $to || $from === $to) {
            return self::normalize($raw);
        }
        $k = $from === 'in' ? self::CM_PER_IN : 1 / self::CM_PER_IN;

        return self::num(round($p[0] * $k, 2)).($p[1] === null ? '' : '-'.self::num(round($p[1] * $k, 2)));
    }

    /**
     * Read-only display text for BOTH units. Converts the exact stored value first (no intermediate rounding), then truncates
     * each end of a range independently to one decimal ("35.96-37.04" in -> 35.9"-37.0"; cm -> 35.9-37.0).
     * Never use this to fill editable inputs: rounded text must not be saved back.
     */
    public static function display(?string $raw, ?string $from, string $to): string
    {
        $p = self::parse($raw);
        if (! $p) {
            return self::format($raw, $to);
        }
        $k = ! $from || $from === $to ? 1.0 : ($from === 'in' ? self::CM_PER_IN : 1 / self::CM_PER_IN);
        $suffix = $to === 'in' ? '"' : '';

        return implode('-', array_map(fn ($n) => self::truncate1($n * $k).$suffix, array_filter($p, fn ($n) => $n !== null)));
    }

    /** Display rule: drop everything after the first decimal (no rounding), always one decimal: 35.96 -> "35.9", 14 -> "14.0". */
    public static function truncate1(float $n): string
    {
        // round(.., 6) first so float noise (e.g. 2.3 * 10 = 22.999999999999996) cannot cost a tenth.
        return number_format(floor(round($n * 10, 6)) / 10, 1, '.', '');
    }

    /** Customer-style text: inch => 28" / 28"-30"; cm => 71.1 / 71.1-76.2 (unit is in the column heading). */
    public static function format(?string $canonical, string $unit): string
    {
        $v = self::normalize($canonical);
        if ($v === '') {
            return '';
        }

        return $unit === 'in' ? str_replace('-', '"-', $v).'"' : $v;
    }
}
