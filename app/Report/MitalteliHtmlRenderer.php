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

use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\ResearchTasksReportModule;
use Fisharebest\Webtrees\Report\HtmlRenderer;

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Webtrees;


use function preg_match;
use function str_replace;


/**
 * Class HtmlRenderer
 */
class MitalteliHtmlRenderer extends HtmlRenderer
{

    function nl2br_outside_tags($html) {

        if (str_contains($html, '>') && str_contains($html, '<')) {
            // This regex matches text between HTML tags.
            // The U modifier makes the quantifier ungreedy.
            return preg_replace_callback(
                '/(>[^<]*<)/U',
                function ($matches) {
                    return nl2br($matches[1]);
                },
                $html
            );
        } else {
            return nl2br($html);
        }


    }

    /**
     * Override textWrap to guard against the infinite loop introduced in webtrees 2.2.6.
     *
     * In webtrees 2.2.6, HtmlRenderer::textWrap() calculates:
     *   $line_width = (int) ($width / ($this->getCurrentStyleHeight() / 2))
     * If this rounds down to 0, utf8WordWrap() enters an infinite loop because
     * mb_substr($string, 0, 0) always returns '' and $string never advances.
     *
     * We guard by ensuring $width is large enough relative to the font height.
     * Using a minimum font height of 6pt (smallest realistic value):
     *   minimum safe $width = 6/2 = 3 points
     * Any visible cell will be wider than 3 points, so this only catches
     * genuinely degenerate widths (0, negative, or sub-pixel).
     *
     * @param string $str
     * @param float  $width
     * @return string
     */
    public function textWrap(string $str, float $width): string
    {
        // Return unwrapped if width is too small to produce a valid line_width >= 1.
        // Minimum font height assumed = 6pt -> minimum safe width = 3.
        if ($width < 3.0) {
            return $str;
        }
        return parent::textWrap($str, $width);
    }

    /**
     * Write text - ReportHtml
     *
     * @param string $text  Text to print
     * @param string $color HTML RGB color code (Ex: #001122)
     * @param bool   $useclass
     *
     * @return void
     */
    public function write(string $text, string $color = '', bool $useclass = true): void
    {
        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->title, ResearchTasksReportModule::REPORT_TITLE)) {
            parent::{__FUNCTION__}($text, $color, $useclass); // Call the parent method and
            return;
        }

        $style    = $this->getStyle($this->getCurrentStyle());
        $htmlcode = '<span dir="' . I18N::direction() . '"';
        if ($useclass) {
            $htmlcode .= ' class="' . $style['name'] . '"';
        }
        // Check if Text Color is set and if it’s valid HTML color
        if (preg_match('/#?(..)(..)(..)/', $color)) {
            $htmlcode .= ' style="color:' . $color . ';"';
        }

        $htmlcode .= '>' . $text . '</span>';
        $htmlcode = str_replace([
            '> ',
            ' <',
        ], [
            '>&nbsp;',
            '&nbsp;<',
        ], $htmlcode);
        $htmlcode = $this->nl2br_outside_tags($htmlcode);
        echo $htmlcode;
    }

    /**
     * Override setY to fix element positioning in webtrees >= 2.2.6.
     *
     * ReportHtmlTextBox::render() saves Y before rendering internal elements,
     * resets it to 0, then restores it after. This means after each TextBox,
     * getY() points to the Y *before* that box — not after it. Subsequent
     * elements with top=CURRENT_POSITION then read this stale value and
     * overlap the previous content.
     *
     * Fix: Y must never go backwards. If something tries to set Y to a value
     * lower than maxY (the highest Y actually written), keep Y at maxY instead.
     * This ensures all elements are always placed below the previous content.
     */
    public function setY(float $y): void
    {
        if (version_compare(Webtrees::VERSION, '2.2.6', '>=') && $y < $this->maxY) {
            $y = $this->maxY;
        }
        parent::setY($y);
    }


    /**
     * Override run() to fix TextBox widths in webtrees >= 2.2.6.
     *
     * The report columns extend to 810pt (with XREF) or 780pt (without XREF),
     * but noMarginWidth for US-Letter is only ~533pt. ReportHtmlTextBox clips
     * its width to getRemainingWidth() = noMarginWidth - X, so section title
     * TextBoxes with width="$sectionWidth" get clipped and don't cover all columns.
     *
     * Fix: before rendering body elements, expand noMarginWidth to the maximum
     * column width so TextBoxes are not clipped. After rendering, restore it.
     * In HTML output, the page margin is cosmetic only - the browser handles layout.
     */
    public function run(): void
    {
        if (version_compare(Webtrees::VERSION, '2.2.6', '>=')) {
            // Expand noMarginWidth to cover the widest possible column layout (810pt).
            // This prevents ReportHtmlTextBox from clipping section title widths.
            $saved              = $this->noMarginWidth;
            $this->noMarginWidth = max($this->noMarginWidth, 810.0);
            parent::run();
            $this->noMarginWidth = $saved;
        } else {
            parent::run();
        }
    }

}
