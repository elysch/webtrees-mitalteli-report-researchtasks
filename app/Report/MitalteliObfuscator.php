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
    private static array $vocales_mayus = ['Á', 'É', 'Í', 'Ó', 'Ú'];
    private static array $vocales_minus = ['á', 'é', 'í', 'ó', 'ú'];

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
                    ? self::$vocales_mayus[array_rand(self::$vocales_mayus)]
                    : self::$vocales_minus[array_rand(self::$vocales_minus)];
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
            '/[^\s\xc2\xa0&<>]+/u',
            fn($w) => $self->obfuscateWord($w[0]),
            $text
        );
    }

    /**
     * Obfuscate all text nodes in an HTML string, preserving tags and attributes.
     * Used by the HTML renderer after generating the full report HTML.
     */
    public function obfuscateHtml(string $html): string
    {
        $self = $this;
        return preg_replace_callback(
            '/>([\\s]*[^<\\s][^<]*?[\\s]*)</',
            function ($m) use ($self) {
                return '>' . preg_replace_callback(
                    '/[^\\s\\xc2\\xa0&<>]+/u',
                    fn($w) => $self->obfuscateWord($w[0]),
                    $m[1]
                ) . '<';
            },
            $html
        );
    }
}
