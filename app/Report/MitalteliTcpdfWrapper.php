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


    /** TCPDF font family of the one-glyph font (resources/fonts) that has the report-title symbol. */
    private const SYMBOL_FONT = 'mitaltelipdfsymbol';

    /** null: not tried yet; true: the symbol font is registered; false: not available (U+2699 is used instead). */
    private ?bool $symbol_font = null;

    private function symbolFontAvailable(): bool
    {
        if ($this->symbol_font === null) {
            $this->symbol_font = false;
            $file = dirname(__DIR__, 2) . '/resources/fonts/' . self::SYMBOL_FONT . '.php';
            if (is_readable($file)) {
                try {
                    foreach (['', 'B', 'I', 'BI'] as $style) {
                        $this->AddFont(self::SYMBOL_FONT, $style, $file);
                    }
                    $this->symbol_font = true;
                } catch (\Throwable $e) {
                    $this->symbol_font = false;
                }
            }
        }

        return $this->symbol_font;
    }

    /**
     * Makes the report-title symbol drawable. The PDF font (DejaVu Sans) has no U+26EE: in HTML text the symbol is
     * drawn with a one-glyph font that has it; if that is not possible, a similar symbol of DejaVu Sans is used.
     */
    private function drawableSymbols(string $text, bool $is_html): string
    {
        // TCPDF writes HTML text through Cell(): text already drawn with the symbol font must not be changed
        if ($this->FontFamily === self::SYMBOL_FONT) {
            return $text;
        }
        if ($text === '' || strpos($text, "\u{26EE}") === false || strpos($text, 'font-family:' . self::SYMBOL_FONT) !== false) {
            return $text;
        }
        if ($is_html && $this->symbolFontAvailable()) {
            return str_replace("\u{26EE}", '<span style="font-family:' . self::SYMBOL_FONT . '">' . "\u{26EE}" . '</span>', $text);
        }

        return ResearchTasksReportModule::pdfSafeText($text);
    }

    private function site_info(): string
    {
        // Check if the report string contains the module directory path
        // if not running from this module, redirect to the standard ReportGenerate handler
        if (!ResearchTasksReportModule::isReportTitle($this->title)) {
            return parent::{__FUNCTION__}(); // Call the parent method
        }

        $site = "";
        $tree = "";
        $value = "";
        if (array_key_exists("HTTP_REFERER", $_SERVER)) {
            // Only trust a Referer that points to THIS host; otherwise any third-party page
            // linking to the report could forge the "site : tree" provenance shown in the PDF footer.
            $ref_host = parse_url((string) $_SERVER["HTTP_REFERER"], PHP_URL_HOST);
            $own_host = parse_url('//' . ($_SERVER["HTTP_HOST"] ?? ''), PHP_URL_HOST);
            if (is_string($ref_host) && is_string($own_host) && strcasecmp($ref_host, $own_host) === 0) {
                $value = (string) preg_replace('/[^A-Za-z0-9\-._~\/?=&:%+,;!*]+/', '', urldecode($_SERVER["HTTP_REFERER"]));
            }
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
        if (!ResearchTasksReportModule::isReportTitle($this->title)) {
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
        if (is_string($html)) {
            $html = $this->drawableSymbols($html, true);
        }
        if ($this->debug_obfuscate && is_string($html)) {
            $html = $this->obf()->obfuscateHtml($html);
        }
        parent::writeHTML($html, $ln, $fill, $reseth, $cell, $align);
    }

    /**
     * Link annotations (individual/family URLs) would reveal real XREFs and the tree name in an
     * "obfuscated" PDF, so they are not written when debug_obfuscate is set.
     */
    public function Link($x, $y, $w, $h, $link, $spaces = 0)
    {
        if ($this->debug_obfuscate) {
            return;
        }
        parent::Link($x, $y, $w, $h, $link, $spaces);
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
        // Plain text cell that starts with the title symbol (TCPDF's own page header uses one for the title of the
        // report): the symbol is drawn with its font in its own cell, followed by the rest of the text.
        $prefix = "\u{26EE} ";
        if (
            is_string($txt)
            && strncmp($txt, $prefix, strlen($prefix)) === 0
            && ($align === '' || $align === 'L')
            && !$this->rtl
            && $this->FontFamily !== self::SYMBOL_FONT
            && $this->symbolFontAvailable()
        ) {
            $family = $this->FontFamily;
            $style  = $this->FontStyle;
            $size   = $this->FontSizePt;
            $this->setFont(self::SYMBOL_FONT, $style, $size);
            $padding = $this->cell_padding['L'] + $this->cell_padding['R'];
            $first_w = $this->GetStringWidth($prefix) + $padding;
            $this->setFont($family, $style, $size);
            if ($w <= 0 || $w > $first_w) {
                $this->setFont(self::SYMBOL_FONT, $style, $size);
                parent::Cell($first_w, $h, $prefix, 0, 0, 'L', $fill, '', 0, $ignore_min_height, $calign, $valign);
                $this->setFont($family, $style, $size);
                $this->Cell($w > 0 ? $w - $first_w : 0, $h, substr($txt, strlen($prefix)), $border, $ln, $align, $fill, $link, $stretch, $ignore_min_height, $calign, $valign);

                return;
            }
        }

        if (is_string($txt)) {
            $txt = $this->drawableSymbols($txt, false);
        }
        if ($this->debug_obfuscate) {
            // Link annotations carry the real site URL, tree name and XREF: drop them (fail closed).
            $link = '';
            if (is_string($txt) && $txt !== '') {
                $txt = $this->obf()->obfuscateText($txt);
            }
        }
        parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link, $stretch,
            $ignore_min_height, $calign, $valign);
    }

    /**
     * Where the row of cells that is being drawn ends, when one of its cells is taller than a page and flowed over
     * several pages: [page, y]. null when there is no such cell.
     *
     * @var array{0:int,1:float}|null
     */
    private ?array $tall_row_end = null;

    /**
     * Same as the parent, plus:
     * - text is made safe for the PDF font (see ResearchTasksReportModule::pdfSafeText());
     * - a cell that does not end in the page where it started (it is taller than the remaining space and flows over
     *   the next pages) is a problem for the cells of the same row. For "newline = 0" cells TCPDF goes back to the
     *   first page and the first Y, so the next row started there, over the text of the long cell. The end of the
     *   long cell is remembered and the NEXT ROW starts there.
     */
    public function MultiCell(
        $w,
        $h,
        $txt,
        $border = 0,
        $align = 'J',
        $fill = false,
        $ln = 1,
        $x = null,
        $y = null,
        $reseth = true,
        $stretch = 0,
        $ishtml = false,
        $autopadding = true,
        $maxh = 0,
        $valign = 'T',
        $fitcell = false
    ) {
        // (HTML text is handled by writeHTML(), which TCPDF calls from here)
        if (is_string($txt) && !$ishtml) {
            $txt = $this->drawableSymbols($txt, false);
        }

        $start_page = $this->getPage();
        $start_x    = ($x === null || $x === '') ? $this->GetX() : (float) $x;
        $start_y    = ($y === null || $y === '') ? $this->GetY() : (float) $y;
        $num_ln     = (int) $ln;

        // Always let TCPDF finish "below" (ln = 1) to learn where the text ends; "ln = 0" is emulated afterwards.
        $result = parent::MultiCell($w, $h, $txt, $border, $align, $fill, $num_ln > 0 ? $ln : 1, $x, $y, $reseth, $stretch, $ishtml, $autopadding, $maxh, $valign, $fitcell);

        $end_page = $this->getPage();
        $end_y    = $this->GetY();

        if ($num_ln > 0) {
            // Last cell of the row: the row ends where its longest cell ended.
            if ($this->tall_row_end !== null) {
                [$row_page, $row_y] = $this->tall_row_end;
                $this->tall_row_end = null;
                if ($row_page > $end_page || ($row_page === $end_page && $row_y > $end_y)) {
                    $this->setPage($row_page);
                    $this->SetY($row_y, false);
                }
            }
            return $result;
        }

        // Emulate ln = 0: back to the beginning of the cell, to the right.
        $this->setPage($start_page);
        $this->SetY($start_y, false);
        $this->SetX($start_x + (float) $w);

        if ($end_page > $start_page) {
            // Flowed over several pages: remember the end, for the next row.
            if (
                $this->tall_row_end === null
                || $end_page > $this->tall_row_end[0]
                || ($end_page === $this->tall_row_end[0] && $end_y > $this->tall_row_end[1])
            ) {
                $this->tall_row_end = [$end_page, $end_y];
            }
        }

        return $result;
    }

    /**
     * While a row has a cell that flowed over several pages, its last height is meaningless for the other cells of the
     * row (they are on the first page): report 0 so they keep their own height.
     */
    public function getLastH()
    {
        return $this->tall_row_end !== null ? 0 : parent::getLastH();
    }
}
