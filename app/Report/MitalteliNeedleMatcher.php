<?php

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report;

use DomainException;

use function mb_strlen;
use function preg_match;
use function preg_quote;
use function preg_split;
use function array_unique;

/**
 * Text matching used by the CONTAINS_R filter ("Match whole string" option).
 *
 * Shared by every webtrees-version-specific parser. PHP 7.4 compatible.
 *
 * - $whole_string = false: the search text is split into words; EVERY word must appear somewhere
 *   in the haystack, in any order, and the amount of white space is irrelevant.
 * - $whole_string = true: the search text must appear exactly as typed (same order, same number
 *   of spaces), as a whole word/phrase: it may not start or end in the middle of a word.
 *
 * Safety: the search text is always passed through preg_quote() and the patterns contain no
 * quantifier applied to user data, so there is no regex injection and no catastrophic
 * backtracking, however many spaces the user types.
 */
final class MitalteliNeedleMatcher
{
    /** Longest search text accepted (the report form already truncates to this length). */
    public const MAX_NEEDLE_CHARS = 100;

    /**
     * @param string $haystack     Text to search (line breaks already replaced by spaces)
     * @param string $needle       Text typed by the user (already without diacritics if requested)
     * @param bool   $whole_string "Match whole string" option
     * @param bool   $ignore_case  "Ignore case" option
     *
     * @throws DomainException when the search text is longer than MAX_NEEDLE_CHARS
     */
    public static function matches(string $haystack, string $needle, bool $whole_string, bool $ignore_case): bool
    {
        if (mb_strlen($needle, 'UTF-8') > self::MAX_NEEDLE_CHARS) {
            throw new DomainException('CONTAINS_R: the search text is longer than ' . self::MAX_NEEDLE_CHARS . ' characters');
        }

        if ($needle === '') {
            return true;
        }

        // Unicode mode only when both strings are valid UTF-8 (otherwise preg_* would fail).
        $unicode = preg_match('//u', $haystack) === 1 && preg_match('//u', $needle) === 1;
        $u       = $unicode ? 'u' : '';
        $flags   = ($ignore_case ? 'i' : '') . $u;
        $word    = $unicode ? '[\p{L}\p{N}_]' : '[A-Za-z0-9_]';

        if ($whole_string) {
            $pattern = preg_quote($needle, '/');

            // Word boundaries only where the text starts/ends with a word character
            // (a plain \b would never match next to a symbol such as "(" or ".").
            if (preg_match('/^' . $word . '/' . $u, $needle) === 1) {
                $pattern = '(?<!' . $word . ')' . $pattern;
            }
            if (preg_match('/' . $word . '\z/' . $u, $needle) === 1) {
                $pattern .= '(?!' . $word . ')';
            }

            return preg_match('/' . $pattern . '/' . $flags, $haystack) === 1;
        }

        $words = preg_split('/\s+/' . $u, $needle, -1, PREG_SPLIT_NO_EMPTY);

        foreach (array_unique($words === false ? [] : $words) as $part) {
            if (preg_match('/' . preg_quote($part, '/') . '/' . $flags, $haystack) !== 1) {
                return false;
            }
        }

        return true;
    }
}
