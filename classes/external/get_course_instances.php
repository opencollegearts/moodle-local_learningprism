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
use local_learningprism\local\mod\module_factory;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * List convertible CMs for a course.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_instances extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
        ]);
    }

    /**
     * @param int $courseid
     * @return array
     */
    public static function execute(int $courseid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        if (!has_any_capability([
            'local/learningprism:download',
            'local/learningprism:request',
            'local/learningprism:manage',
        ], $context)) {
            throw new \required_capability_exception($context, 'local/learningprism:download', 'nopermissions', '');
        }

        if (!manager::plugin_enabled() || !manager::course_enabled($params['courseid'])) {
            return ['instances' => []];
        }

        return ['instances' => module_factory::get_course_instances($params['courseid'])];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'instances' => new external_multiple_structure(
                new external_single_structure([
                    'cmid' => new external_value(PARAM_INT, 'CM id'),
                    'modname' => new external_value(PARAM_ALPHA, 'Module name'),
                    'name' => new external_value(PARAM_TEXT, 'Activity name'),
                    'url' => new external_value(PARAM_URL, 'Activity URL'),
                    'contenthash' => new external_value(PARAM_ALPHANUM, 'Content hash'),
                    'state' => new external_value(PARAM_ALPHA, 'Button state'),
                ])
            ),
        ]);
    }
}
