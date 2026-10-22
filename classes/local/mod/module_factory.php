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

namespace local_learningprism\local\mod;

use local_learningprism\manager;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Factory for activity module extractors.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class module_factory {
    /** @var string[] */
    private static $map = [
        'book' => mod_book::class,
        'page' => mod_page::class,
        'label' => mod_label::class,
        'scorm' => mod_scorm::class,
    ];

    /**
     * @param int $cmid
     * @return activity_module
     * @throws moodle_exception
     */
    public static function instance_from_cmid(int $cmid): activity_module {
        $cm = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);
        if (!isset(self::$map[$cm->modname])) {
            throw new moodle_exception('error:unsupportedmodule', 'local_learningprism');
        }
        $class = self::$map[$cm->modname];
        return new $class($cm);
    }

    /**
     * Course module data for UI injection.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_course_instances(int $courseid): array {
        if (!manager::course_enabled($courseid)) {
            return [];
        }

        $modinfo = get_fast_modinfo($courseid);
        $instances = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if (!in_array($cm->modname, manager::supported_modules(), true)) {
                continue;
            }
            $context = \context_module::instance($cm->id);
            if (!has_capability('local/learningprism:download', $context) &&
                    !has_capability('local/learningprism:request', $context)) {
                continue;
            }
            try {
                $module = self::instance_from_cmid($cm->id);
                $hash = $module->get_content_hash();
                $state = \local_learningprism\job_manager::button_state($cm->id, $hash);
                $instances[] = [
                    'cmid' => (int) $cm->id,
                    'modname' => $cm->modname,
                    'name' => $cm->name,
                    'url' => $cm->url ? $cm->url->out(false) : (new \moodle_url('/mod/' . $cm->modname . '/view.php',
                        ['id' => $cm->id]))->out(false),
                    'contenthash' => $hash,
                    'state' => $state,
                ];
            } catch (\Throwable $e) {
                error_log('local_learningprism skip cmid ' . $cm->id . ': ' . $e->getMessage());
            }
        }
        return $instances;
    }
}
