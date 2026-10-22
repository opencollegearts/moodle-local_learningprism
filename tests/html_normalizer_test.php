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

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for HTML normaliser.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_learningprism\html_normalizer
 */
class html_normalizer_test extends \advanced_testcase {
    /**
     * Tests normalise adds title and lang.
     */
    public function test_normalise_adds_title_and_lang(): void {
        $this->resetAfterTest();
        $html = html_normalizer::normalise('My Page', '<p>Hello</p><script>alert(1)</script>', 'en-gb');
        $this->assertStringContainsString('<html lang="en-gb">', $html);
        $this->assertStringContainsString('<h1>My Page</h1>', $html);
        $this->assertStringContainsString('<p>Hello</p>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    /**
     * Tests content hash stable.
     */
    public function test_content_hash_stable(): void {
        $this->resetAfterTest();
        $html = html_normalizer::normalise('T', '<p>x</p>');
        $this->assertSame(html_normalizer::content_hash($html), html_normalizer::content_hash($html));
        $other = html_normalizer::normalise('T', '<p>y</p>');
        $this->assertNotSame(html_normalizer::content_hash($html), html_normalizer::content_hash($other));
    }

    /**
     * Tests print css includes background preset.
     */
    public function test_print_css_includes_background_preset(): void {
        $this->resetAfterTest();
        set_config('printbackground', 'cream', 'local_learningprism');
        $css = html_normalizer::print_css();
        $this->assertStringContainsString('#FBF3D5', $css);
        $this->assertSame('#FBF3D5', html_normalizer::print_background_hex());
    }

    /**
     * Tests print css applies font and style override.
     */
    public function test_print_css_applies_font_and_style_override(): void {
        $this->resetAfterTest();
        $css = html_normalizer::print_css('blue', 'verdana');
        $this->assertStringContainsString('#E8F4FC', $css);
        $this->assertStringContainsString('Verdana', $css);
        $html = html_normalizer::normalise('T', '<p>Hi</p>');
        $styled = html_normalizer::apply_print_style($html, ['printbackground' => 'pink', 'font' => 'arial']);
        $this->assertStringContainsString('#FDECEC', $styled);
        $this->assertStringContainsString('Arial', $styled);
        $this->assertSame(
            html_normalizer::print_options_hash(['printbackground' => 'cream', 'font' => 'verdana']),
            html_normalizer::print_options_hash(['printbackground' => 'cream', 'font' => 'verdana'])
        );
    }

    /**
     * Tests to speech text.
     */
    public function test_to_speech_text(): void {
        $this->resetAfterTest();
        $html = '<h1>Title</h1><p>One sentence.</p><p>Two.</p>';
        $text = html_normalizer::to_speech_text($html);
        $this->assertStringContainsString('Title', $text);
        $this->assertStringContainsString('One sentence', $text);
    }
}
