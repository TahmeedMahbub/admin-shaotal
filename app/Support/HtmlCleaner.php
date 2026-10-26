<?php

namespace App\Support;

/** Reduces rich text to basic formatting: allowed tags only, no attributes except safe link hrefs. */
class HtmlCleaner
{
    private const ALLOWED = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><blockquote><a>';

    public static function clean(?string $html): ?string
    {
        $html = trim(strip_tags((string) $html, self::ALLOWED));
        $html = preg_replace_callback('/<(\w+)\s+([^>]*)>/', function ($m) {
            if (strtolower($m[1]) === 'a' && preg_match('/href\s*=\s*"((?:https?:\/\/|mailto:|\/)[^"]*)"/i', $m[2], $h)) {
                return '<a href="'.e(html_entity_decode($h[1])).'" target="_blank" rel="noopener noreferrer">';
            }

            return '<'.$m[1].'>';
        }, $html);
        // Empty wrappers left behind by removed markup (e.g. <p></p>, <li><p></p></li>).
        $html = preg_replace('#<(p|li|ul|ol)>\s*</\1>#i', '', $html);

        return trim($html) === '' ? null : trim($html);
    }
}
