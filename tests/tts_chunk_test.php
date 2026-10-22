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

use local_learningprism\local\tts\google_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * TTS chunking tests.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_learningprism\local\tts\google_provider
 */
class tts_chunk_test extends \advanced_testcase {
    /**
     * Tests chunk text splits long content.
     */
    public function test_chunk_text_splits_long_content(): void {
        $this->resetAfterTest();
        $text = str_repeat('Sentence one. ', 200);
        $chunks = google_provider::chunk_text($text, 100);
        $this->assertGreaterThan(1, count($chunks));
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(120, \core_text::strlen($chunk));
        }
    }
}
