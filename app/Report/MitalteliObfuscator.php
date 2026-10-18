<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2025 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report;

/**
 * PURPOSE (read before "fixing" anything): this is a PRIVACY feature, not a debugging aid that leaks.
 * It exists so users can share the look of a report with strangers WITHOUT revealing its content,
 * which is why it is deliberately available to every user who can run the report. It hides data; it
 * never reveals more than the real report would. It must fail closed (refuse rather than output real data).
 *
 * Text obfuscation utility for debug mode.
 *
 * Replaces each word with random letters of the exact same character length,
 * preserving spaces, punctuation, accented characters (every 5 chars),
 * and uppercase every 75 chars — so word-wrap behaves identically to real content.
 *
 * Used both for HTML (replacing text nodes) and PDF (replacing write() output).
 */
class MitalteliObfuscator
{
    private static array $accented_upper = ['Á', 'É', 'Í', 'Ó', 'Ú'];
    private static array $accented_lower = ['á', 'é', 'í', 'ó', 'ú'];

    private int $counter = 0;

    /**
     * Obfuscate a single word, preserving its character length.
     * Every 5th character is a random accented vowel (upper or lower).
     * Every 75th character is uppercase, others are lowercase.
     */
    public function obfuscateWord(string $word): string
    {
        $len    = mb_strlen($word);
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $this->counter++;

            if ($this->counter % 5 === 0) {
                $result .= ($this->counter % 75 === 0)
                    ? self::$accented_upper[array_rand(self::$accented_upper)]
                    : self::$accented_lower[array_rand(self::$accented_lower)];
            } else {
                $result .= ($this->counter % 75 === 0)
                    ? chr(mt_rand(65, 90))
                    : chr(mt_rand(97, 122));
            }
        }

        return $result;
    }

    /**
     * Obfuscate a plain text string word by word, preserving all whitespace.
     * Used by the PDF renderer's write() method.
     */
    public function obfuscateText(string $text): string
    {
        $self = $this;
        return preg_replace_callback(
            '/[^\s\x{00A0}&<>]+/u',
            fn($w) => $self->obfuscateWord($w[0]),
            $text
        );
    }

    /**
     * Text-node obfuscation never reaches ATTRIBUTES, which can carry real data
     * (2.3 auto-links names: href="https://site/tree/<tree-name>/individual/<xref>"; title/alt text;
     * inline data: images). Neutralise them so an obfuscated report does not leak through markup.
     * In-page anchors (href="#footnote1") are kept so footnote links keep working.
     */
    public function scrubAttributes(string $html): string
    {
        $html = preg_replace_callback(
            '/(\s)(href|title|alt)\s*=\s*("|\')(.*?)\3/is',
            static function (array $m): string {
                $name = strtolower($m[2]);
                if ($name === 'href' && strncmp(ltrim($m[4]), '#', 1) === 0) {
                    return $m[0];
                }
                return $m[1] . $m[2] . '=' . $m[3] . ($name === 'href' ? '#' : '') . $m[3];
            },
            $html
        ) ?? $html;

        return preg_replace_callback(
            '/(\ssrc\s*=\s*)("|\')data:[^"\']*\2/i',
            static function (array $m): string {
                // 1x1 transparent GIF: hides the picture, keeps the box (inline width/height survive)
                return $m[1] . $m[2] . 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7' . $m[2];
            },
            $html
        ) ?? $html;
    }

    /**
     * Obfuscate all text nodes in an HTML string, preserving tags and attributes.
     * Used by the HTML renderer after generating the full report HTML.
     */
    public function obfuscateHtml(string $html): string
    {
        $self = $this;
        $html = $this->scrubAttributes($html);
        return preg_replace_callback(
            '/>([\\s]*[^<\\s][^<]*?[\\s]*)</',
            function ($m) use ($self) {
                return '>' . preg_replace_callback(
                    '/[^\\s\\x{00A0}&<>]+/u',
                    fn($w) => $self->obfuscateWord($w[0]),
                    $m[1]
                ) . '<';
            },
            $html
        );
    }
}
