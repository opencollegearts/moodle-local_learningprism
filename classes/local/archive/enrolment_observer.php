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

namespace local_learningprism\local\archive;

defined('MOODLE_INTERNAL') || die();

/**
 * Refresh Drive archive participant sharing when enrolments change.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_observer {
    /**
     * @param \core\event\base $event
     */
    public static function user_enrolment_changed(\core\event\base $event): void {
        $courseid = (int) $event->courseid;
        if ($courseid <= 0) {
            return;
        }
        sync_manager::queue_participant_share_refresh($courseid);
    }
}
