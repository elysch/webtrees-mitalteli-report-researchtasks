<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2026 webtrees development team
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
 * Same rationale as MitalteliHtmlRenderer_2_3: core's PdfRenderer is now
 * `final`, so we extend the open AbstractRenderer + ElementFactoryInterface
 * + PdfRenderTargetInterface surface instead, mirroring core's PdfRenderer.
 *
 * OBFUSCATION ($debug_obfuscate) - supported, and public on purpose:
 * it is a privacy feature that HIDES data so a report's look can be shared with strangers, so every
 * user who can run the report may use it (see README). The 2.2 approach (swapping the TCPDF wrapper)
 * is impossible here because TcLibPdfAdaptor is `final`; instead every output path of
 * PdfRenderTargetInterface that can carry data is neutralised in this class, AFTER layout, so wrapping
 * and pagination match the real report:
 *   - drawTextBlock(): text replaced char-by-char with width-matched random letters
 *     (MitalteliPdfTextObfuscator);
 *   - addLinkArea():   external URLs (they contain the tree name and XREFs) are dropped; only
 *                      internal footnote links, which are numeric ids, are kept;
 *   - drawImage():     not drawn;
 *   - PDF metadata (title/description/author) comes from the static report definition, no personal data.
 * FAIL CLOSED: if a new text/link/image path is ever added to PdfRenderTargetInterface, it MUST be
 * handled here too, otherwise obfuscated PDFs would leak.
 */

use Com\Tecnick\Pdf\Tcpdf;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Enums\ImageOperation;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Webtrees;
use vendor\WebtreesModules\mitalteli\ResearchTasksReportNamespace\ResearchTasksReportModule;

use Fisharebest\Webtrees\Report\AbstractRenderer;
use Fisharebest\Webtrees\Report\Cell;
use Fisharebest\Webtrees\Report\CellAlign;
use Fisharebest\Webtrees\Report\CellNewline;
use Fisharebest\Webtrees\Report\Config;
use Fisharebest\Webtrees\Report\ElementFactoryInterface;
use Fisharebest\Webtrees\Report\Footnote;
use Fisharebest\Webtrees\Report\HexColor;
use Fisharebest\Webtrees\Report\Image;
use Fisharebest\Webtrees\Report\ImageContinuation;
use Fisharebest\Webtrees\Report\Line;
use Fisharebest\Webtrees\Report\PdfRenderTargetInterface;
use Fisharebest\Webtrees\Report\PdfWriter;
use Fisharebest\Webtrees\Report\Style;
use Fisharebest\Webtrees\Report\TcLibPdfAdaptor;
use Fisharebest\Webtrees\Report\TextBox;
use Fisharebest\Webtrees\Report\Text;

use RuntimeException;

use function defined;
use function define;
use function is_dir;
use function realpath;
use function strtoupper;

class MitalteliPdfRenderer_2_3 extends AbstractRenderer implements ElementFactoryInterface, PdfRenderTargetInterface
{
    private const bool UNICODE = true;

    private TcLibPdfAdaptor $adaptor;

    private readonly PdfWriter $pdf_writer;

    /** When true, the PDF is produced with all personal data hidden (see class docblock). */
    public bool $debug_obfuscate = false;

    private ?MitalteliPdfTextObfuscator $text_obfuscator = null;

    /** Width of $text in the CURRENT PDF font. Protected so it can be replaced in tests. */
    protected function measureForObfuscation(string $text): float
    {
        return $this->adaptor->getStringWidth($text);
    }

    private function obfuscateBlockText(string $text): string
    {
        $this->text_obfuscator ??= new MitalteliPdfTextObfuscator(fn (string $s): float => $this->measureForObfuscation($s));

        // The key identifies the font actually in use (family/style/size => different widths).
        $font_key = (string) round($this->measureForObfuscation('abcdefghijklmnopqrstuvwxyz'), 4);

        return $this->text_obfuscator->obfuscate($text, $font_key);
    }

    public function __construct()
    {
        parent::__construct();
        $this->pdf_writer = new PdfWriter();
    }

    public function header(float $origin_x = 0.0, float $origin_y = 0.0): void
    {
        $saved_style = $this->currentStyleValue();
        $this->pdf_writer->renderFixedSection($this, $this->headerElements(), $origin_x, $origin_y);
        $this->restoreCurrentStyle($saved_style);
    }

    public function body(): void
    {
        $this->pdf_writer->renderBody($this, $this->bodyElements());
    }

    public function footer(float $origin_x = 0.0, float $origin_y = 0.0): void
    {
        $saved_style = $this->currentStyleValue();
        $this->pdf_writer->renderFixedSection($this, $this->footerElements(), $origin_x, $origin_y);
        $this->restoreCurrentStyle($saved_style);
    }

    private function restoreCurrentStyle(Style|null $saved_style): void
    {
        $this->setCurrentStyleValue($saved_style);
        if ($saved_style !== null) {
            $this->adaptor->setFont($this->config->font, strtoupper($saved_style->style), $saved_style->size);
        }
    }

    public function setCurrentStyle(Style $style): void
    {
        if ($this->currentStyleValue() !== $style) {
            $this->setCurrentStyleValue($style);
            $this->adaptor->setFont($this->config->font, strtoupper($style->style), $style->size);
        }
    }

    public function pageNumber(): int
    {
        return $this->adaptor->pageNumber();
    }

    public function newPage(): void
    {
        $this->adaptor->addPage();
    }

    public function setup(Config $config): void
    {
        parent::setup($config);

        if (!defined('K_PATH_FONTS')) {
            $font_path = realpath(Webtrees::ROOT_DIR . 'resources/fonts');

            if ($font_path === false || !is_dir($font_path)) {
                throw new RuntimeException('Unable to resolve PDF font directory: ' . Webtrees::ROOT_DIR . 'resources/fonts');
            }

            define('K_PATH_FONTS', $font_path);
        }

        $this->tcpdf = $tcpdf = new Tcpdf(
            self::UNITS,
            self::UNICODE,
            $config->font_subsetting,
            $config->compression,
            '',
            null,
            [
                'allowedHosts' => [],
            ],
        );

        $tcpdf->setPageTransparencyGroup('auto');

        $this->adaptor = new TcLibPdfAdaptor($tcpdf, $this, $this->config);
    }

    public function output(): string
    {
        $this->body();

        return $this->adaptor->output();
    }

    public function createCell(float $width, float $height, string $border, CellAlign $align, string $background_color, Style $style, CellNewline $newline, float $top, float $left, string $border_color, string $text_color): Cell
    {
        return new Cell($width, $height, $border, $align, $background_color, $style, $newline, $top, $left, $border_color, $text_color);
    }

    public function createTextBox(
        float $width,
        float $height,
        bool $border,
        string $background_color,
        bool $newline,
        float $left,
        float $top,
        bool $check_page_break,
        bool $padding,
        bool $reset_height,
    ): TextBox {
        return new TextBox($width, $height, $border, $background_color, $newline, $left, $top, $check_page_break, $padding, $reset_height);
    }

    public function createText(Style $style, string $color, float $truncate): Text
    {
        return new Text($style, $color, $truncate);
    }

    public function createFootnote(Style $style): Footnote
    {
        return new Footnote($style);
    }

    public function createImage(
        string $mime_type,
        string $data,
        float $x,
        float $y,
        float $w,
        float $h,
        CellAlign $align,
        ImageContinuation $ln,
    ): Image {
        $src = '@' . $data;

        return new Image($src, $x, $y, $w, $h, $align, $ln);
    }

    public function createImageFromObject(
        MediaFile $media_file,
        float $x,
        float $y,
        float $w,
        float $h,
        CellAlign $align,
        ImageContinuation $ln,
    ): Image {
        $data = Registry::imageFactory()->mediaFileThumbnail(
            $media_file,
            (int) ($w * 4),
            (int) ($h * 4),
            ImageOperation::Crop,
            Auth::needsWatermark($media_file->media()->tree()),
        );

        $src = '@' . $data;

        return new Image($src, $x, $y, $w, $h, $align, $ln);
    }

    public function createLine(float $x1, float $y1, float $x2, float $y2): Line
    {
        return new Line($x1, $y1, $x2, $y2);
    }

    public function getStringWidth(string $text): float
    {
        return $this->adaptor->getStringWidth($text);
    }

    public function getPageIndex(): int
    {
        return $this->adaptor->getPage();
    }

    public function isRTL(): bool
    {
        return $this->adaptor->getRTL();
    }

    public function setFillColor(HexColor $color): void
    {
        $this->adaptor->setFillColor($color);
    }

    public function setDrawColor(HexColor $color): void
    {
        $this->adaptor->setDrawColor($color);
    }

    public function setTextColor(HexColor $color): void
    {
        $this->text_color_hex = $color->hex();
        $this->adaptor->setTextColor($color);
    }

    public function resetColors(): void
    {
        $this->text_color_hex = '#000000';
        $this->adaptor->resetColors();
    }

    public function drawImage(string $file, float $x, float $y, float $width, float $height): void
    {
        if ($this->debug_obfuscate) {
            return; // pictures can show real people: never draw them in an obfuscated report
        }

        $this->adaptor->drawImage($file, $x, $y, $width, $height);
    }

    public function drawLine(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->adaptor->drawLine($x1, $y1, $x2, $y2);
    }

    public function drawRect(float $x, float $y, float $width, float $height, string $style): void
    {
        $this->adaptor->drawRect($x, $y, $width, $height, $style);
    }

    /** The symbol of the report title, followed by the space that separates it from the title. */
    /** Baseline of the text, in em from the top of the line (calibrated against DejaVu Sans, see tests). */
    private const float SYMBOL_BASELINE = 0.93;

    private const string SYMBOL_PREFIX = ResearchTasksReportModule::TITLE_PREFIX;

    /** Advance width of the symbol, in em (1 = the font size). */
    private const float SYMBOL_ADVANCE = 0.989;

    /**
     * Outline of U+26EE (GEAR WITHOUT HUB) in em units (y up, baseline at 0), from Noto Sans Symbols (SIL Open Font
     * License 1.1, see resources/fonts/OFL-NotoSansSymbols.txt). Operators of a PDF path separated by "|":
     * m = move, l = line, c = cubic curve, h = close.
     *
     * The PDF font bundled with webtrees (DejaVu Sans) has no glyph for it and tc-lib-pdf-font only loads fonts from the
     * webtrees font directory, so the symbol is drawn as a vector shape: nothing has to be installed outside the module.
     */
    private const string SYMBOL_PATH = 'm 0.47 0|l 0.47 0.151|c 0.422 0.1563 0.381 0.1733 0.347 0.202|l 0.24 0.095|l 0.204 0.131|l 0.311 0.238|c 0.2823 0.2733 0.2653 0.3143 0.26 0.361|l 0.101 0.361|l 0.101 0.241|l 0.051 0.241|l 0.051 0.411|l 0.26 0.411|c 0.2653 0.457 0.2823 0.498 0.311 0.534|l 0.204 0.641|l 0.24 0.677|l 0.347 0.57|c 0.3803 0.5987 0.4213 0.6153 0.47 0.62|l 0.47 0.772|l 0.52 0.772|l 0.52 0.62|c 0.5667 0.6147 0.6077 0.598 0.643 0.57|l 0.75 0.677|l 0.786 0.641|l 0.679 0.534|c 0.707 0.5 0.7237 0.459 0.729 0.411|l 0.888 0.411|l 0.888 0.531|l 0.938 0.531|l 0.938 0.361|l 0.729 0.361|c 0.7243 0.3137 0.7077 0.2727 0.679 0.238|l 0.786 0.131|l 0.75 0.095|l 0.643 0.202|c 0.607 0.1733 0.566 0.1563 0.52 0.151|l 0.52 0|h|m 0.495 0.207|c 0.5277 0.207 0.5575 0.215 0.5845 0.231|c 0.6115 0.247 0.6332 0.2687 0.6495 0.296|c 0.6658 0.3233 0.674 0.3533 0.674 0.386|c 0.674 0.4193 0.6658 0.4495 0.6495 0.4765|c 0.6332 0.5035 0.6115 0.525 0.5845 0.541|c 0.5575 0.557 0.5277 0.565 0.495 0.565|c 0.4623 0.565 0.4325 0.5568 0.4055 0.5405|c 0.3785 0.5242 0.3568 0.5025 0.3405 0.4755|c 0.3242 0.4485 0.316 0.4187 0.316 0.386|c 0.316 0.3533 0.3242 0.3235 0.3405 0.2965|c 0.3568 0.2695 0.3785 0.2478 0.4055 0.2315|c 0.4325 0.2152 0.4623 0.207 0.495 0.207|h';

    private Tcpdf $tcpdf;

    private string $text_color_hex = '#000000';

    /** Draws the symbol with its left edge at $x and its baseline at $baseline (user units, y down). */
    private function drawSymbol(float $x, float $baseline, float $size): void
    {
        $out = 'q ' . $this->tcpdf->color->getPdfColor($this->text_color_hex, false);
        foreach (explode('|', self::SYMBOL_PATH) as $segment) {
            $parts = explode(' ', $segment);
            $op    = array_shift($parts);
            foreach ($parts as $i => $value) {
                $em    = (float) $value;
                $parts[$i] = $i % 2 === 0
                    ? sprintf('%.3F', $this->tcpdf->toPoints($x) + $em * $this->tcpdf->toPoints($size))
                    : sprintf('%.3F', $this->tcpdf->toYPoints($baseline) + $em * $this->tcpdf->toPoints($size));
            }
            $out .= implode(' ', $parts) . ($parts === [] ? '' : ' ') . $op . "\n";
        }
        $out .= "f\nQ\n";

        $this->tcpdf->page->addContent($out);
    }

    public function drawTextBlock(string $text, float $x, float $y, float $width, float $height, string $align, float $line_height, bool $with_padding = true): void
    {
        $style = $this->currentStyleValue();

        // The title of the report starts with a symbol that the PDF font does not have: it is drawn as a shape, just
        // before the rest of the text. Only for left aligned text in left-to-right pages; in the other cases a similar
        // symbol of the PDF font (U+2699) is used.
        if (
            $style instanceof Style
            && !$this->is_rtl_page()
            && ($align === 'L' || $align === '')
            && str_starts_with($text, self::SYMBOL_PREFIX)
        ) {
            $pad    = $with_padding ? 1.0 : 0.0; // TcLibPdfAdaptor::CELL_PADDING
            $offset = self::SYMBOL_ADVANCE * $style->size + $this->adaptor->getStringWidth(' ');
            if ($width - $offset > 2 * $pad && $height >= $line_height) {
                $this->drawSymbol($x + $pad, $y + $pad + self::SYMBOL_BASELINE * $style->size, $style->size);
                $this->drawTextBlock(substr($text, strlen(self::SYMBOL_PREFIX)), $x + $offset, $y, $width - $offset, $height, $align, $line_height, $with_padding);

                return;
            }
        }

        // The PDF font has no glyph for the report-title symbol (U+26EE): use a similar one that it has.
        $text = ResearchTasksReportModule::pdfSafeText($text);

        if ($this->debug_obfuscate) {
            $text = $this->obfuscateBlockText($text);
        }

        $this->adaptor->drawTextBlock($text, $x, $y, $width, $height, $align, $line_height, $with_padding);
    }

    private function is_rtl_page(): bool
    {
        return $this->adaptor->getRTL();
    }

    public function addLinkArea(float $x, float $y, float $width, float $height, string $url): void
    {
        // Internal links (footnotes) are numeric registry ids; anything else is an external URL that
        // contains the tree name and XREFs, so it is dropped when obfuscating.
        if ($this->debug_obfuscate && !ctype_digit($url)) {
            return;
        }

        $this->adaptor->addLinkArea($x, $y, $width, $height, $url);
    }

    public function createLink(): int
    {
        return $this->adaptor->createInternalLink();
    }

    public function setLinkDestination(string $link, float $y, int $page = -1): void
    {
        $this->adaptor->setLinkDestination($link, $y, $page);
    }
}
