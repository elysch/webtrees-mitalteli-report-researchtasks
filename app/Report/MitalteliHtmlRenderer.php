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
}
