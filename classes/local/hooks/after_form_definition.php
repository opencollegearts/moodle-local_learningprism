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

namespace local_learningprism\local\hooks;

use core_course\hook\after_form_definition as after_form_definition_hook;
use local_learningprism\course_settings;

defined('MOODLE_INTERNAL') || die();

/**
 * Course form definition hook (Moodle 4.4+ / 4.5).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_form_definition {
    /**
     * @param after_form_definition_hook $hook
     */
    public static function callback(after_form_definition_hook $hook): void {
        try {
            $courseid = 0;
            $course = $hook->formwrapper->get_course();
            if (!empty($course->id)) {
                $courseid = (int) $course->id;
            }
            course_settings::add_form_fields($hook->mform, $courseid);
        } catch (\Throwable $e) {
            error_log('local_learningprism after_form_definition: ' . $e->getMessage());
        }
    }
}
