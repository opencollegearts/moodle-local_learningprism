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
 * Course settings form helpers.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_settings {
    /**
     * Add enable checkbox to course edit form.
     *
     * @param \MoodleQuickForm $mform
     * @param int $courseid 0 when creating a course.
     */
    public static function add_form_fields(\MoodleQuickForm $mform, int $courseid = 0): void {
        if (!manager::plugin_enabled()) {
            return;
        }
        $mform->addElement('header', 'learningprismheader', get_string('pluginname', 'local_learningprism'));
        $mform->addElement(
            'advcheckbox',
            'learningprism_enabled',
            get_string('course:enable', 'local_learningprism')
        );
        $mform->addHelpButton('learningprism_enabled', 'course:enable', 'local_learningprism');
        $enabled = $courseid ? manager::course_enabled($courseid) : false;
        $mform->setDefault('learningprism_enabled', $enabled ? 1 : 0);
    }

    /**
     * Persist enable flag after course save.
     *
     * @param \stdClass $course
     */
    public static function save_from_course(\stdClass $course): void {
        if (!manager::plugin_enabled()) {
            return;
        }
        if (!isset($course->learningprism_enabled)) {
            return;
        }
        manager::set_course_enabled((int) $course->id, !empty($course->learningprism_enabled));
    }
}
