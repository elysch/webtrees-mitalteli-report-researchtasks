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
 * Trait MitalteliObfuscateTrait
 *
 * Provides text obfuscation for debug mode (?debug_obfuscate=1).
 * Replaces each character with a random letter of the same case,
 * inserting accented vowels every 5 characters to mimic natural
 * Spanish text rhythm. Preserves whitespace and word boundaries
 * so word-wrap behaviour is identical to the real content.
 *
 * Used by both MitalteliHtmlRenderer and MitalteliPdfRenderer_2_1.
 */
trait MitalteliObfuscateTrait
{
    /** Counter shared across all words to distribute uppercase/accented chars evenly. */
    private int $obf_counter = 0;

    /**
     * Obfuscate a single word, preserving its character count exactly.
     *
     * @param string $word  The word to obfuscate (no whitespace).
     * @return string       Random letters of the same length.
     */
    private function obfuscateWord(string $word): string
    {
        $len           = mb_strlen($word);
        $result        = '';
        $vocales_mayus = ['Á', 'É', 'Í', 'Ó', 'Ú'];
        $vocales_minus = ['á', 'é', 'í', 'ó', 'ú'];

        for ($i = 0; $i < $len; $i++) {
            $this->obf_counter++;

            if ($this->obf_counter % 5 === 0) {
                // Every 5th character: accented vowel
                $result .= ($this->obf_counter % 75 === 0)
                    ? $vocales_mayus[array_rand($vocales_mayus)]   // uppercase accented
                    : $vocales_minus[array_rand($vocales_minus)];  // lowercase accented
            } else {
                // Otherwise: plain random letter
                $result .= ($this->obf_counter % 75 === 0)
                    ? chr(mt_rand(65, 90))   // uppercase
                    : chr(mt_rand(97, 122)); // lowercase
            }
        }

        return $result;
    }

    /**
     * Obfuscate all words in a plain text string.
     * Preserves all whitespace, &nbsp;, and non-word characters.
     *
     * @param string $text  Plain text (no HTML tags).
     * @return string       Obfuscated text, same whitespace structure.
     */
    public function obfuscateText(string $text): string
    {
        return preg_replace_callback(
            '/[^\s\xc2\xa0&<>]+/u',
            fn($w) => $this->obfuscateWord($w[0]),
            $text
        );
    }

    /**
     * Obfuscate all visible text nodes in an HTML string.
     * Only replaces text between tags; tag names, attributes, and
     * entities are left untouched so layout is preserved exactly.
     *
     * @param string $html  Full HTML string.
     * @return string       HTML with all visible text obfuscated.
     */
    public function obfuscateHtml(string $html): string
    {
        $this->obf_counter = 0;

        return preg_replace_callback(
            '/>([\s]*[^<\s][^<]*?[\s]*)</',
            fn($m) => '>' . $this->obfuscateText($m[1]) . '<',
            $html
        );
    }
}
