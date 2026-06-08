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

use IntlDateFormatter;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Report\TcpdfWrapper;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\Report\MitalteliObfuscator;

/**
 * Class TcpdfWrapper
 */
class MitalteliTcpdfWrapper extends TcpdfWrapper
{
    private function site_info(): string
    {
        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->title, ResearchTasksReportModule::REPORT_TITLE)) {
            return parent::{__FUNCTION__}(); // Call the parent method
        }

        $site = "";
        $tree = "";
        $value = "";
        if (array_key_exists("HTTP_REFERER", $_SERVER)) {
            $value = filter_var(urldecode($_SERVER["HTTP_REFERER"]), FILTER_SANITIZE_URL);
        }
        $url1 = "";
        $i = strpos($value, "route=");
        if ($i !== false) {
            $url1 = substr($value, 0, $i + 6);
            $value = substr($value, $i + 6);
        }
        $i = strpos($value, "/tree/");
        if ($i !== false) {
            $site = substr($value, 0, $i);
            $tree = substr($value, $i + 6);
            $i = strpos($tree, "/");
            $tree = substr($tree, 0, $i);
        }
        return $site."  :  ".$tree;
    }


    public function Footer() {
        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!str_contains($this->title, ResearchTasksReportModule::REPORT_TITLE)) {
            parent::{__FUNCTION__}(); // Call the parent method and
            return;
        }

        $pw = $this->getPageWidth();
        $marg = $this->getMargins();
        $lmarg = $marg['left'];
        $rmarg = $marg['right'];
        $dt = date_create("now");
        $lang = Session::get("language");
        $fmt = datefmt_create( $lang, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE);
        // Position at 20 mm from bottom
        $this->SetY(-20);
        // Set font
        $cY = $this->GetY() - 3;
        $this->Line($lmarg, $cY, $pw - $rmarg, $cY);
        $this->SetFont('helvetica', 'I', 8);
        // Date
        $this->setRightMargin($pw - $lmarg - 50); // cell width=50
        $this->Cell(0, 10, datefmt_format($fmt, $dt), 0, false, 'L', 0, '', 0, false, 'T', 'M');
        // Tree
        $this->setRightMargin($rmarg + 30); // leave 30 pt to page number
        $this->Cell(0, 10, $this->site_info(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        // Page number
        $this->setRightMargin($rmarg);
        $this->SetFont('helvetica', '', 10);
        $this->Cell(0, 10, $this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'L', 0, '', 0, false, 'T', 'M');
    }
    /** When true, all text is obfuscated before rendering. */
    public bool $debug_obfuscate = false;

    /** Shared obfuscator instance. */
    private ?MitalteliObfuscator $obfuscator = null;

    /** Get or create the obfuscator. */
    private function obf(): MitalteliObfuscator
    {
        if ($this->obfuscator === null) {
            $this->obfuscator = new MitalteliObfuscator();
        }
        return $this->obfuscator;
    }

    /**
     * Override writeHTML to obfuscate text content when debug_obfuscate is set.
     * Called by ReportPdfText::render() for all text elements.
     */
    public function writeHTML(
        $html,
        $ln = true,
        $fill = false,
        $reseth = false,
        $cell = false,
        $align = ''
    ): void {
        if ($this->debug_obfuscate && is_string($html)) {
            $html = $this->obf()->obfuscateHtml($html);
        }
        parent::writeHTML($html, $ln, $fill, $reseth, $cell, $align);
    }

    /**
     * Override Cell to obfuscate text when debug_obfuscate is set.
     * Called by ReportPdfCell::render() for cell elements.
     */
    public function Cell(
        $w,
        $h = 0,
        $txt = '',
        $border = 0,
        $ln = 0,
        $align = '',
        $fill = false,
        $link = '',
        $stretch = 0,
        $ignore_min_height = false,
        $calign = 'T',
        $valign = 'M'
    ): void {
        if ($this->debug_obfuscate && is_string($txt) && $txt !== '') {
            $txt = $this->obf()->obfuscateText($txt);
        }
        parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch,
            $ignore_min_height, $calign, $valign);
    }


}
