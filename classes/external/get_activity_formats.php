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
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Format statuses for one activity.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_activity_formats extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/learningprism:download', $context);

        if (!manager::plugin_enabled() || !manager::course_enabled((int) $cm->course)) {
            throw new \moodle_exception('error:notenabled', 'local_learningprism');
        }

        return job_manager::get_activity_format_statuses($params['cmid']);
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'CM id'),
            'name' => new external_value(PARAM_TEXT, 'Name'),
            'contenthash' => new external_value(PARAM_ALPHANUM, 'Hash'),
            'state' => new external_value(PARAM_ALPHA, 'State'),
            'printbackground' => new external_value(PARAM_ALPHA, 'User background preset'),
            'font' => new external_value(PARAM_ALPHANUMEXT, 'User font preset'),
            'backgrounds' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHA, 'Preset key'),
                    'label' => new external_value(PARAM_TEXT, 'Label'),
                    'selected' => new external_value(PARAM_BOOL, 'Selected'),
                ])
            ),
            'fonts' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHANUMEXT, 'Preset key'),
                    'label' => new external_value(PARAM_TEXT, 'Label'),
                    'selected' => new external_value(PARAM_BOOL, 'Selected'),
                ])
            ),
            'formats' => new external_multiple_structure(
                new external_single_structure([
                    'format' => new external_value(PARAM_ALPHA, 'Format'),
                    'label' => new external_value(PARAM_TEXT, 'Label'),
                    'status' => new external_value(PARAM_ALPHA, 'Status'),
                    'jobid' => new external_value(PARAM_INT, 'Job id'),
                    'errorinfo' => new external_value(PARAM_RAW, 'Error'),
                    'downloadurl' => new external_value(PARAM_RAW, 'Download URL'),
                    'supportspersonal' => new external_value(PARAM_BOOL, 'Print format can be personalised'),
                    'personalstatus' => new external_value(PARAM_ALPHA, 'Personal job status'),
                    'personaljobid' => new external_value(PARAM_INT, 'Personal job id'),
                    'personalerrorinfo' => new external_value(PARAM_RAW, 'Personal error'),
                    'personaldownloadurl' => new external_value(PARAM_RAW, 'Personal download URL'),
                ])
            ),
        ]);
    }
}
