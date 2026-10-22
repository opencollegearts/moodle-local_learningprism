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
 * Download URLs for ready formats on an activity.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_download_url extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'format' => new external_value(PARAM_ALPHA, 'Optional single format', VALUE_DEFAULT, ''),
            'personal' => new external_value(PARAM_BOOL, 'Download this user\'s private version', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * @param int $cmid
     * @param string $format
     * @param bool $personal
     * @return array
     */
    public static function execute(int $cmid, string $format = '', bool $personal = false): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'format' => $format,
            'personal' => $personal,
        ]);
        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/learningprism:download', $context);

        $statuses = job_manager::get_activity_format_statuses($params['cmid']);
        $files = [];
        foreach ($statuses['formats'] as $item) {
            if ($params['format'] && $item['format'] !== $params['format']) {
                continue;
            }
            $ready = $params['personal'] ? ($item['personalstatus'] ?? '') : $item['status'];
            $url = $params['personal'] ? ($item['personaldownloadurl'] ?? '') : $item['downloadurl'];
            $jobid = $params['personal'] ? ($item['personaljobid'] ?? 0) : $item['jobid'];
            if ($ready !== manager::STATUS_READY || empty($url)) {
                continue;
            }
            if (!empty($jobid)) {
                job_manager::log_request((int) $jobid, (int) $USER->id, manager::REQUEST_TYPE_DOWNLOAD);
            }
            $files[] = [
                'format' => $item['format'],
                'label' => $item['label'],
                'url' => $url,
            ];
        }
        return ['files' => $files];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'format' => new external_value(PARAM_ALPHA, 'Format'),
                    'label' => new external_value(PARAM_TEXT, 'Label'),
                    'url' => new external_value(PARAM_RAW, 'URL'),
                ])
            ),
        ]);
    }
}
