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

use local_learningprism\local\notebook\source_collector;

defined('MOODLE_INTERNAL') || die();

/**
 * Notebook source collector tests.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_learningprism\local\notebook\source_collector
 */
class notebook_collector_test extends \advanced_testcase {
    /**
     * Supported file names map to upload MIME types.
     */
    public function test_mime_for_supported_files(): void {
        $this->resetAfterTest();
        $this->assertSame('application/pdf', source_collector::mime_for_name('notes.PDF', 'application/octet-stream'));
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            source_collector::mime_for_name('essay.docx', '')
        );
        $this->assertSame('audio/mpeg', source_collector::mime_for_name('talk.mp3', 'audio/mp3'));
        $this->assertSame('video/mp4', source_collector::mime_for_name('clip.mp4', 'video/mp4'));
    }

    /**
     * Unsupported files are ignored.
     */
    public function test_mime_for_unsupported_files(): void {
        $this->resetAfterTest();
        $this->assertNull(source_collector::mime_for_name('image.png', 'image/png'));
        $this->assertNull(source_collector::mime_for_name('slides.pptx', ''));
        $this->assertNull(source_collector::mime_for_name('.', 'application/pdf'));
    }
}
