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

use local_learningprism\local\converter\epub_converter;

defined('MOODLE_INTERNAL') || die();

/**
 * EPUB converter tests.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_learningprism\local\converter\epub_converter
 */
class epub_converter_test extends \advanced_testcase {
    /**
     * Tests build epub package structure.
     */
    public function test_build_epub_package_structure(): void {
        $this->resetAfterTest();
        $converter = new epub_converter();
        $this->assertTrue($converter->is_configured());

        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $html = html_normalizer::normalise(
            'Ebook Title',
            '<h2>Chapter</h2><p>Body text</p><p><img src="data:image/png;base64,' . $png . '" alt="dot"/></p>'
        );
        $binary = $converter->build_epub($html, 'Ebook Title');
        $this->assertNotEmpty($binary);
        $this->assertSame('PK', substr($binary, 0, 2));

        $tmpdir = make_request_directory();
        $path = $tmpdir . '/test.epub';
        file_put_contents($path, $binary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('mimetype'));
        $this->assertSame('application/epub+zip', $zip->getFromName('mimetype'));
        $this->assertNotFalse($zip->locateName('META-INF/container.xml'));
        $this->assertNotFalse($zip->locateName('EPUB/content.opf'));
        $this->assertNotFalse($zip->locateName('EPUB/nav.xhtml'));
        $this->assertNotFalse($zip->locateName('EPUB/chapter.xhtml'));
        $this->assertNotFalse($zip->locateName('EPUB/styles.css'));
        $this->assertNotFalse($zip->locateName('EPUB/images/img1.png'));
        $opf = $zip->getFromName('EPUB/content.opf');
        $this->assertStringContainsString('version="3.0"', $opf);
        $this->assertStringContainsString('Ebook Title', $opf);
        $nav = $zip->getFromName('EPUB/nav.xhtml');
        $this->assertStringContainsString('epub:type="toc"', $nav);
        $this->assertStringContainsString('Chapter', $nav);
        $css = $zip->getFromName('EPUB/styles.css');
        $this->assertStringContainsString('background-color', $css);
        $zip->close();
    }
}
