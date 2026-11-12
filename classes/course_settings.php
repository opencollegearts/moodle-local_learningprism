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

use local_learningprism\local\tts\voice_catalog;

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
     * Add enable checkbox and audio/voice fields to course edit form.
     *
     * @param \MoodleQuickForm $mform
     * @param int $courseid 0 when creating a course.
     */
    public static function add_form_fields(\MoodleQuickForm $mform, int $courseid = 0): void {
        if (!manager::plugin_enabled()) {
            return;
        }
        $record = $courseid ? manager::get_course_record($courseid) : (object) [
            'enabled' => 0,
            'allowstudentaudio' => 0,
            'defaultvoice' => '',
        ];

        $mform->addElement('header', 'learningprismheader', get_string('pluginname', 'local_learningprism'));
        $mform->addElement(
            'advcheckbox',
            'learningprism_enabled',
            get_string('course:enable', 'local_learningprism')
        );
        $mform->addHelpButton('learningprism_enabled', 'course:enable', 'local_learningprism');
        $mform->setDefault('learningprism_enabled', !empty($record->enabled) ? 1 : 0);

        $mform->addElement(
            'advcheckbox',
            'learningprism_allowstudentaudio',
            get_string('course:allowstudentaudio', 'local_learningprism')
        );
        $mform->addHelpButton('learningprism_allowstudentaudio', 'course:allowstudentaudio', 'local_learningprism');
        $mform->setDefault('learningprism_allowstudentaudio', !empty($record->allowstudentaudio) ? 1 : 0);
        $mform->disabledIf('learningprism_allowstudentaudio', 'learningprism_enabled');

        $voiceoptions = ['' => get_string('course:defaultvoice_site', 'local_learningprism')];
        foreach (voice_catalog::options_for_provider(voice_catalog::active_provider()) as $id => $label) {
            $voiceoptions[$id] = $label;
        }
        $mform->addElement(
            'select',
            'learningprism_defaultvoice',
            get_string('course:defaultvoice', 'local_learningprism'),
            $voiceoptions
        );
        $mform->addHelpButton('learningprism_defaultvoice', 'course:defaultvoice', 'local_learningprism');
        $mform->setDefault('learningprism_defaultvoice', (string) ($record->defaultvoice ?? ''));
        $mform->disabledIf('learningprism_defaultvoice', 'learningprism_enabled');
    }

    /**
     * Persist settings after course save.
     *
     * @param \stdClass $course
     */
    public static function save_from_course(\stdClass $course): void {
        if (!manager::plugin_enabled()) {
            return;
        }
        if (!isset($course->learningprism_enabled)
                && !isset($course->learningprism_allowstudentaudio)
                && !isset($course->learningprism_defaultvoice)) {
            return;
        }
        manager::save_course_settings((int) $course->id, [
            'enabled' => !empty($course->learningprism_enabled),
            'allowstudentaudio' => !empty($course->learningprism_allowstudentaudio),
            'defaultvoice' => (string) ($course->learningprism_defaultvoice ?? ''),
        ]);
    }
}
