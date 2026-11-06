<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_learningprism;

use local_learningprism\local\converter\docx_converter;
use local_learningprism\local\converter\epub_converter;
use local_learningprism\local\converter\image_helper;

defined('MOODLE_INTERNAL') || die();

/**
 * SVG rasterisation helpers.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_learningprism\local\converter\image_helper
 */
class image_helper_test extends \advanced_testcase {
    /**
     * Sample Rise-style flashcard SVG.
     *
     * @return string
     */
    private function sample_svg(): string {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 676.17 676.17">'
            . '<rect fill="#ff8a78" width="676.17" height="676.17"/>'
            . '<path d="M268.84,235.09v-79.5h138.5v365h-89V235.09h-49.5Z"/>'
            . '</svg>';
    }

    /**
     * Tests viewBox parsing.
     */
    public function test_svg_dimensions_from_viewbox(): void {
        [$width, $height] = image_helper::svg_dimensions($this->sample_svg());
        $this->assertSame(676, $width);
        $this->assertSame(676, $height);
    }

    /**
     * Tests DOCX/EPUB embed SVG as PNG when a rasteriser is available.
     */
    public function test_docx_and_epub_embed_svg_as_png(): void {
        $this->resetAfterTest();
        // Prefer the local Gotenberg used for PDF when present.
        if (!get_config('local_learningprism', 'pdfengineurl')) {
            set_config('pdfengineurl', 'http://127.0.0.1:3000', 'local_learningprism');
        }

        $png = image_helper::svg_to_png($this->sample_svg());
        if ($png === null) {
            $this->markTestSkipped('No SVG rasteriser available (Imagick, Gotenberg, or rsvg-convert).');
        }
        $this->assertSame("\x89PNG", substr($png, 0, 4));

        $datauri = 'data:image/svg+xml;base64,' . base64_encode($this->sample_svg());
        $html = html_normalizer::normalise('SVG', '<p><img src="' . $datauri . '" alt="card"/></p>');

        $docx = (new docx_converter())->build_docx($html, 'SVG');
        $tmpdir = make_request_directory();
        $docxpath = $tmpdir . '/svg.docx';
        file_put_contents($docxpath, $docx);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($docxpath) === true);
        $this->assertNotFalse($zip->locateName('word/media/image1.png'));
        $this->assertFalse($zip->locateName('word/media/image1.svg'));
        $zip->close();

        $epub = (new epub_converter())->build_epub($html, 'SVG');
        $epubpath = $tmpdir . '/svg.epub';
        file_put_contents($epubpath, $epub);
        $zip2 = new \ZipArchive();
        $this->assertTrue($zip2->open($epubpath) === true);
        $this->assertNotFalse($zip2->locateName('EPUB/images/img1.png'));
        $this->assertFalse($zip2->locateName('EPUB/images/img1.svg'));
        $this->assertFalse($zip2->locateName('EPUB/images/img1.svgxml'));
        $zip2->close();
    }
}
