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
        $accented_upper = ['Á', 'É', 'Í', 'Ó', 'Ú'];
        $accented_lower = ['á', 'é', 'í', 'ó', 'ú'];

        for ($i = 0; $i < $len; $i++) {
            $this->obf_counter++;

            if ($this->obf_counter % 5 === 0) {
                // Every 5th character: accented vowel
                $result .= ($this->obf_counter % 75 === 0)
                    ? $accented_upper[array_rand($accented_upper)]   // uppercase accented
                    : $accented_lower[array_rand($accented_lower)];  // lowercase accented
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
            '/[^\s\x{00A0}&<>]+/u',
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
        $html = (new MitalteliObfuscator())->scrubAttributes($html);

        return preg_replace_callback(
            '/>([\s]*[^<\s][^<]*?[\s]*)</',
            fn($m) => '>' . $this->obfuscateText($m[1]) . '<',
            $html
        );
    }
}
