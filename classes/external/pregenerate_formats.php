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

namespace local_learningprism\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_learningprism\job_manager;
use local_learningprism\local\mod\module_factory;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Teacher pre-generate formats for course activities.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pregenerate_formats extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'cmids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'CM id'),
                'Optional CM ids; empty = all supported',
                VALUE_DEFAULT,
                []
            ),
            'formats' => new external_multiple_structure(
                new external_value(PARAM_ALPHA, 'Format'),
                'Formats to generate',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * @param int $courseid
     * @param int[] $cmids
     * @param string[] $formats
     * @return array
     */
    public static function execute(int $courseid, array $cmids = [], array $formats = []): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'cmids' => $cmids,
            'formats' => $formats,
        ]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/learningprism:manage', $context);

        if (!manager::plugin_enabled() || !manager::course_enabled($params['courseid'])) {
            throw new \moodle_exception('error:notenabled', 'local_learningprism');
        }

        $formats = $params['formats'] ?: manager::enabled_formats();
        $targets = $params['cmids'];
        if (!$targets) {
            $instances = module_factory::get_course_instances($params['courseid']);
            $targets = array_column($instances, 'cmid');
        }

        $queued = 0;
        foreach ($targets as $cmid) {
            $cm = get_coursemodule_from_id(null, $cmid, $params['courseid'], false, IGNORE_MISSING);
            if (!$cm) {
                continue;
            }
            $jobs = job_manager::queue_formats((int) $cmid, $formats, (int) $USER->id);
            $queued += count($jobs);
        }

        return ['queued' => $queued];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'queued' => new external_value(PARAM_INT, 'Number of jobs queued/touched'),
        ]);
    }
}
