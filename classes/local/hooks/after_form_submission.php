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

use core_course\hook\after_form_submission as after_form_submission_hook;
use local_learningprism\course_settings;

defined('MOODLE_INTERNAL') || die();

/**
 * Persist course Learning Prism setting after course form submit.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class after_form_submission {
    /**
     * @param after_form_submission_hook $hook
     */
    public static function callback(after_form_submission_hook $hook): void {
        try {
            $data = $hook->get_data();
            if (!$data || empty($data->id)) {
                return;
            }
            course_settings::save_from_course($data);
        } catch (\Throwable $e) {
            error_log('local_learningprism after_form_submission: ' . $e->getMessage());
        }
    }
}
