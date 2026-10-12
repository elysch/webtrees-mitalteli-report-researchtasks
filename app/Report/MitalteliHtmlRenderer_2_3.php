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
 * IMPORTANT — why this class does NOT "extend HtmlRenderer" like the 2.0/2.1 versions did:
 *
 * As of webtrees >= 2.3 (the `main` branch as of 2026-09), the whole report
 * engine was rewritten. `Fisharebest\Webtrees\Report\HtmlRenderer` is now
 * declared `final class HtmlRenderer extends AbstractRenderer implements ElementFactoryInterface`.
 * PHP does not allow extending a `final` class, so the old
 * "MitalteliHtmlRenderer extends HtmlRenderer" pattern is impossible here.
 *
 * The webtrees team left `AbstractRenderer` (abstract, NOT final) as the real
 * extension point: it already implements everything a renderer needs
 * EXCEPT output(), setCurrentStyle(), getStringWidth(), newPage(), pageNumber(),
 * and the create*() factory methods from ElementFactoryInterface. Core's
 * HtmlRenderer only fills in exactly those methods, and every one of them is
 * a small, self-contained factory/pipeline method with no hidden coupling to
 * a private base class we'd need reflection to reach. So instead of
 * "composition via a private delegate object", we get true, safe reuse by
 * extending AbstractRenderer directly and mirroring core's HtmlRenderer body,
 * which is copied here verbatim except where noted.
 *
 * If webtrees changes this file again, only the body of output()/create*()
 * needs re-diffing against Fisharebest\Webtrees\Report\HtmlRenderer — nothing
 * here reaches into core internals via reflection.
 */

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Enums\ImageOperation;
use Fisharebest\Webtrees\MediaFile;
use Fisharebest\Webtrees\Registry;

use Fisharebest\Webtrees\Report\AbstractRenderer;
use Fisharebest\Webtrees\Report\Cell;
use Fisharebest\Webtrees\Report\CellAlign;
use Fisharebest\Webtrees\Report\CellNewline;
use Fisharebest\Webtrees\Report\Config;
use Fisharebest\Webtrees\Report\ElementFactoryInterface;
use Fisharebest\Webtrees\Report\Footnote;
use Fisharebest\Webtrees\Report\HtmlTextMeasurer;
use Fisharebest\Webtrees\Report\HtmlWriter;
use Fisharebest\Webtrees\Report\Image;
use Fisharebest\Webtrees\Report\ImageContinuation;
use Fisharebest\Webtrees\Report\LayoutEngine;
use Fisharebest\Webtrees\Report\Line;
use Fisharebest\Webtrees\Report\PageOrientation;
use Fisharebest\Webtrees\Report\Style;
use Fisharebest\Webtrees\Report\StyleDefaults;
use Fisharebest\Webtrees\Report\Text;
use Fisharebest\Webtrees\Report\TextBox;

use function base64_encode;
use function mb_strlen;

class MitalteliHtmlRenderer_2_3 extends AbstractRenderer implements ElementFactoryInterface
{
    /**
     * When true, the rendered HTML is passed through MitalteliObfuscator
     * before being returned. NOTE: this is the ONE piece of the old
     * MitalteliHtmlRenderer that is fully portable as-is, because
     * obfuscation happened on the finished HTML string, not on any
     * internal streaming state (unlike the old setY()/textWrap() overrides
     * -- see the class docblock in MitalteliParserGenerate_2_3 for why
     * those are NOT ported here).
     */
    public bool $debug_obfuscate = false;

    public function output(): string
    {
        $config = $this->reportConfig();

        $layout_page_width = $config->orientation === PageOrientation::Landscape
            ? $config->paper_height
            : $config->paper_width;

        // HTML uses infinite page height (no automatic page breaks).
        $layout_config = new Config(
            paper_width: $layout_page_width,
            paper_height: 0.0,
            left_margin: $config->left_margin,
            right_margin: $config->right_margin,
            top_margin: $config->top_margin,
            bottom_margin: $config->bottom_margin,
            header_margin: $config->header_margin,
            footer_margin: $config->footer_margin,
            orientation: $config->orientation,
            paper_size: $config->paper_size,
            rtl: $config->rtl,
            generated_by: $config->generated_by,
            author: $config->author,
            title: $config->title,
            description: $config->description,
            align_rtl: $config->align_rtl,
            entity_rtl: $config->entity_rtl,
            font: $config->font,
            timestamp: $config->timestamp,
            font_subsetting: $config->font_subsetting,
            compression: $config->compression,
        );

        $measurer = new HtmlTextMeasurer();
        $engine   = new LayoutEngine($measurer, $layout_config);

        $header_pages = $engine->layoutPaged($this->headerElements());
        $body_pages   = $engine->layoutPaged($this->bodyElements());
        $footer_pages = $engine->layoutPaged($this->footerElements());

        $writer = new HtmlWriter();
        $html   = $writer->renderPaged(
            $config,
            $this->stylesMap(),
            $header_pages,
            $body_pages,
            $footer_pages,
        );

        if ($this->debug_obfuscate) {
            $html = (new MitalteliObfuscator())->obfuscateHtml($html);
        }

        return $html;
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
        $src = 'data:' . $mime_type . ';base64,' . base64_encode($data);

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

        $src = 'data:' . $media_file->mimeType() . ';base64,' . base64_encode($data);

        return new Image($src, $x, $y, $w, $h, $align, $ln);
    }

    public function createLine(float $x1, float $y1, float $x2, float $y2): Line
    {
        return new Line($x1, $y1, $x2, $y2);
    }

    public function newPage(): void
    {
        // Page breaks are handled by the LayoutEngine pipeline.
    }

    public function setCurrentStyle(Style $style): void
    {
        $this->setCurrentStyleValue($style);
    }

    public function getStringWidth(string $text): float
    {
        $style = $this->currentStyleValue();

        $font_size = $style instanceof Style ? $style->size : StyleDefaults::DEFAULT_FONT_SIZE;

        return mb_strlen($text) * $font_size / 2;
    }

    public function pageNumber(): int
    {
        return 1;
    }
}
