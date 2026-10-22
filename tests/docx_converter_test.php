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

defined('MOODLE_INTERNAL') || die();

/**
 * DOCX converter tests.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_learningprism\local\converter\docx_converter
 */
class docx_converter_test extends \advanced_testcase {
    /**
     * Tests build docx is zip.
     */
    public function test_build_docx_is_zip(): void {
        $this->resetAfterTest();
        $converter = new docx_converter();
        $this->assertTrue($converter->is_configured());
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
        $html = html_normalizer::normalise(
            'Doc',
            '<h2>Section</h2><p>Body text</p>'
            . '<figure><img src="data:image/png;base64,' . $png . '" alt="dot"/><figcaption>A caption</figcaption></figure>'
        );
        $binary = $converter->build_docx($html, 'Doc');
        $this->assertNotEmpty($binary);
        // ZIP magic bytes.
        $this->assertSame('PK', substr($binary, 0, 2));
        $tmpdir = make_request_directory();
        $path = $tmpdir . '/test.docx';
        file_put_contents($path, $binary);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path) === true);
        $document = $zip->getFromName('word/document.xml');
        $this->assertStringContainsString('w:background', $document);
        $this->assertStringContainsString('w:val="Heading1"', $document);
        $this->assertStringContainsString('w:val="Heading2"', $document);
        $this->assertStringContainsString('w:drawing', $document);
        $this->assertStringContainsString('A caption', $document);
        $this->assertNotFalse($zip->locateName('word/settings.xml'));
        $this->assertNotFalse($zip->locateName('word/media/image1.png'));
        $styles = $zip->getFromName('word/styles.xml');
        $this->assertStringContainsString('w:qFormat', $styles);
        $this->assertStringContainsString('w:styleId="Heading2"', $styles);
        $styled = $converter->build_docx($html, 'Doc', ['printbackground' => 'cream', 'font' => 'verdana']);
        $path2 = $tmpdir . '/test2.docx';
        file_put_contents($path2, $styled);
        $zip2 = new \ZipArchive();
        $this->assertTrue($zip2->open($path2) === true);
        $styles2 = $zip2->getFromName('word/styles.xml');
        $this->assertStringContainsString('Verdana', $styles2);
        $doc2 = $zip2->getFromName('word/document.xml');
        $this->assertStringContainsString('FBF3D5', $doc2);
        $zip2->close();
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        $this->assertStringContainsString('relationships/image', $rels);
        $zip->close();
    }

    /**
     * Tests safe filename.
     */
    public function test_safe_filename(): void {
        $this->assertSame('Hello_World', docx_converter::safe_filename('Hello/World'));
    }
}
