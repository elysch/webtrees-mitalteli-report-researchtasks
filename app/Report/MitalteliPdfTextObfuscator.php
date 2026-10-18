<?php

declare(strict_types=1);

namespace vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report;

use function array_slice;
use function abs;
use function array_keys;
use function array_map;
use function arsort;
use function asort;
use function reset;
use function count;
use function implode;
use function preg_match;
use function preg_replace;
use function preg_split;
use function random_int;

/**
 * Width-preserving text obfuscation for the webtrees 2.3 PDF backend.
 *
 * PURPOSE (read before "fixing" anything): this is a PRIVACY feature, not a debugging aid that leaks.
 * It lets users share the look of a report with strangers WITHOUT revealing its content, which is why
 * it is deliberately available to every user who can run the report. It hides data; it never reveals
 * more than the real report would. It must fail closed (throw/refuse rather than output real data).
 *
 * Why not reuse MitalteliObfuscator? In 2.3 the PDF layout is computed from the REAL text, but
 * TcLibPdfAdaptor::drawTextBlock() wraps the text it receives AGAIN. If the replacement letters were
 * wider than the originals, lines would re-wrap differently or get cut. So every printable character is
 * replaced by a random lowercase letter whose width, in the CURRENT font, is among the closest to the
 * original character's width and never wider than it. Whitespace and zero-width/combining/format characters are kept as they are.
 *
 * Residual leakage (accepted by design, for layout fidelity): the width of each character, hence word
 * lengths and word count.
 */
final class MitalteliPdfTextObfuscator
{
    /** Replacement alphabet: lowercase letters plus accented vowels (Spanish rhythm). */
    private const POOL = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'á', 'é', 'í', 'ó', 'ú'];

    /** How many width-closest letters are candidates for each character. */
    private const CANDIDATES = 4;

    /** @var callable(string): float */
    private $measure;

    /** @var array<string,array<string,float>> font key => letter => width */
    private array $pool_widths = [];

    /** @var array<string,array<string,array<int,string>>> font key => character => candidate letters */
    private array $candidates = [];

    /**
     * @param callable(string): float $measure Width of a string in the CURRENT font.
     */
    public function __construct(callable $measure)
    {
        $this->measure = $measure;
    }

    /**
     * @param string $font_key Identifies the current font/style/size (widths are cached per key).
     */
    public function obfuscate(string $text, string $font_key): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($chars === false) {
            // Not valid UTF-8: fail closed, blank every non-space byte.
            return (string) preg_replace('/\S/', 'x', $text);
        }

        $out = [];

        foreach ($chars as $char) {
            if (preg_match('/^[\s\p{Mn}\p{Me}\p{Cf}]$/u', $char) === 1) {
                $out[] = $char;
                continue;
            }

            $candidates = $this->candidatesFor($char, $font_key);
            $out[]      = $candidates[random_int(0, count($candidates) - 1)];
        }

        return implode('', $out);
    }

    /**
     * @return array<int,string>
     */
    private function candidatesFor(string $char, string $font_key): array
    {
        if (isset($this->candidates[$font_key][$char])) {
            return $this->candidates[$font_key][$char];
        }

        if (!isset($this->pool_widths[$font_key])) {
            $widths = [];
            foreach (self::POOL as $letter) {
                $widths[$letter] = (float) ($this->measure)($letter);
            }
            $this->pool_widths[$font_key] = $widths;
        }

        $target = (float) ($this->measure)($char);

        // Prefer letters that are NOT wider than the original character: TcLibPdfAdaptor::drawTextBlock()
        // re-wraps what it receives, so text that is narrower can never add lines or get cut, but wider can.
        $below = [];
        foreach ($this->pool_widths[$font_key] as $letter => $width) {
            if ($width <= $target + 1.0E-6) {
                $below[(string) $letter] = $width;
            }
        }

        if ($below !== []) {
            arsort($below);
            $best    = reset($below);
            $letters = [];
            foreach ($below as $letter => $width) {
                if ($width >= $best - 0.12 * $target) {
                    $letters[] = (string) $letter;
                }
            }
        } else {
            // Narrower than every letter (e.g. an apostrophe): take the narrowest ones.
            $distance = [];
            foreach ($this->pool_widths[$font_key] as $letter => $width) {
                $distance[(string) $letter] = abs($width - $target);
            }
            asort($distance);
            $letters = array_map('strval', array_slice(array_keys($distance), 0, self::CANDIDATES));
        }

        return $this->candidates[$font_key][$char] = $letters;
    }
}
