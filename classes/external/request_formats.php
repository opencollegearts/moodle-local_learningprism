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
 * Queue missing formats for an activity.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_formats extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'formats' => new external_multiple_structure(
                new external_value(PARAM_ALPHA, 'Format key'),
                'Formats to request',
                VALUE_DEFAULT,
                []
            ),
            'personal' => new external_value(PARAM_BOOL, 'Request a private print version', VALUE_DEFAULT, false),
            'printbackground' => new external_value(PARAM_ALPHA, 'Personal background preset', VALUE_DEFAULT, ''),
            'font' => new external_value(PARAM_ALPHANUMEXT, 'Personal font preset', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * @param int $cmid
     * @param string[] $formats
     * @param bool $personal
     * @param string $printbackground
     * @param string $font
     * @return array
     */
    public static function execute(
        int $cmid,
        array $formats = [],
        bool $personal = false,
        string $printbackground = '',
        string $font = ''
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'formats' => $formats,
            'personal' => $personal,
            'printbackground' => $printbackground,
            'font' => $font,
        ]);
        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/learningprism:request', $context);

        if (!manager::plugin_enabled() || !manager::course_enabled((int) $cm->course)) {
            throw new \moodle_exception('error:notenabled', 'local_learningprism');
        }

        $requested = $params['formats'];
        if (!$requested) {
            $requested = manager::enabled_formats();
        }

        $forceregenerate = !$params['personal'] && has_capability('local/learningprism:manage', $context);
        $jobs = job_manager::queue_formats(
            $params['cmid'],
            $requested,
            (int) $USER->id,
            $forceregenerate,
            (bool) $params['personal'],
            [
                'printbackground' => $params['printbackground'],
                'font' => $params['font'],
            ]
        );
        $statuses = job_manager::get_activity_format_statuses($params['cmid']);

        return [
            'queued' => count($jobs),
            'state' => $statuses['state'],
            'formats' => $statuses['formats'],
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'queued' => new external_value(PARAM_INT, 'Jobs touched'),
            'state' => new external_value(PARAM_ALPHA, 'Button state'),
            'formats' => new external_multiple_structure(
                new external_single_structure([
                    'format' => new external_value(PARAM_ALPHA, 'Format'),
                    'label' => new external_value(PARAM_TEXT, 'Label'),
                    'status' => new external_value(PARAM_ALPHA, 'Status'),
                    'jobid' => new external_value(PARAM_INT, 'Job id'),
                    'errorinfo' => new external_value(PARAM_RAW, 'Error'),
                    'downloadurl' => new external_value(PARAM_RAW, 'Download URL'),
                ])
            ),
        ]);
    }
}
