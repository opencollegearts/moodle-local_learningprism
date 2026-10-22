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
 * Manager / course enable tests.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_learningprism\manager
 */
class manager_test extends \advanced_testcase {
    /**
     * Tests course enable toggle.
     */
    public function test_course_enable_toggle(): void {
        $this->resetAfterTest();
        set_config('enable', 1, 'local_learningprism');
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse(manager::course_enabled($course->id));
        manager::set_course_enabled($course->id, true);
        $this->assertTrue(manager::course_enabled($course->id));
        manager::set_course_enabled($course->id, false);
        $this->assertFalse(manager::course_enabled($course->id));
    }

    /**
     * Tests enabled formats respects pdf engine.
     */
    public function test_enabled_formats_respects_pdf_engine(): void {
        $this->resetAfterTest();
        set_config('enabledocx', 1, 'local_learningprism');
        set_config('enablepdf', 1, 'local_learningprism');
        set_config('enableepub', 1, 'local_learningprism');
        set_config('enableaudio', 0, 'local_learningprism');
        set_config('pdfengine', 'none', 'local_learningprism');
        $formats = manager::enabled_formats();
        $this->assertContains(manager::FORMAT_DOCX, $formats);
        $this->assertContains(manager::FORMAT_EPUB, $formats);
        $this->assertNotContains(manager::FORMAT_PDF, $formats);
        set_config('pdfengine', 'gotenberg', 'local_learningprism');
        $formats = manager::enabled_formats();
        $this->assertContains(manager::FORMAT_PDF, $formats);
        set_config('enableepub', 0, 'local_learningprism');
        $formats = manager::enabled_formats();
        $this->assertNotContains(manager::FORMAT_EPUB, $formats);
    }

    /**
     * Tests supported modules include scorm.
     */
    public function test_supported_modules_include_scorm(): void {
        $this->assertContains('scorm', manager::supported_modules());
        $this->assertContains('book', manager::supported_modules());
    }
}
