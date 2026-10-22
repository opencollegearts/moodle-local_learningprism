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

/**
 * Callbacks and pluginfile for local_learningprism.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_learningprism\filemanager;
use local_learningprism\job_manager;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Serve converted format files.
 *
 * @param stdClass $course
 * @param stdClass|null $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_learningprism_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $USER;

    if ($filearea !== manager::FILEAREA) {
        return false;
    }
    if (!manager::plugin_enabled()) {
        return false;
    }

    require_login($course, false, $cm);

    $jobid = (int) array_shift($args);
    $filename = clean_param(array_shift($args), PARAM_FILE);
    if (!$jobid || !$filename) {
        return false;
    }

    $job = job_manager::get_job($jobid);
    if (!$job || $job->status !== manager::STATUS_READY) {
        return false;
    }

    $modcontext = context_module::instance($job->cmid);
    if ($context->contextlevel !== CONTEXT_MODULE || (int) $context->instanceid !== (int) $job->cmid) {
        return false;
    }
    require_capability('local/learningprism:download', $modcontext);

    if (!job_manager::user_can_access_job($job, (int) $USER->id)) {
        return false;
    }

    if (!manager::course_enabled((int) $job->courseid)) {
        return false;
    }

    $file = filemanager::get_job_file($job);
    if (!$file || $file->get_filename() !== $filename) {
        return false;
    }

    job_manager::log_request((int) $job->id, (int) $USER->id, manager::REQUEST_TYPE_DOWNLOAD);

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}

/**
 * Extend course navigation with report / pre-generate links.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_learningprism_extend_navigation_course($navigation, $course, $context) {
    if (!manager::plugin_enabled()) {
        return;
    }
    if (has_capability('local/learningprism:manage', $context)) {
        $navigation->add(
            get_string('course:enable', 'local_learningprism'),
            new moodle_url('/local/learningprism/course.php', ['id' => $course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'local_learningprism_enable'
        );
    }
    if (!manager::course_enabled($course->id)) {
        return;
    }
    if (has_capability('local/learningprism:viewreport', $context)) {
        $url = new moodle_url('/local/learningprism/report.php', ['id' => $course->id]);
        $navigation->add(
            get_string('course:report', 'local_learningprism'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_learningprism_report'
        );
    }
    if (has_capability('local/learningprism:manage', $context)) {
        $url = new moodle_url('/local/learningprism/pregenerate.php', ['id' => $course->id]);
        $navigation->add(
            get_string('course:pregenerate', 'local_learningprism'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'local_learningprism_pregenerate'
        );
    }
}

/**
 * Inject course settings checkbox (legacy callback for broader Moodle support).
 *
 * @param moodleform $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_learningprism_coursemodule_standard_elements($formwrapper, $mform) {
    // Not used for course modules; course form uses hook / course_extended_form.
}

/**
 * Add fields to the course edit form.
 *
 * @param moodleform_mod|moodleform $formwrapper
 * @param MoodleQuickForm $mform
 */
function local_learningprism_course_extended_form($formwrapper, $mform) {
    // Implemented via classes/local/hooks/course_form.php where available.
}

// Intentionally no local_learningprism_before_standard_html_head().
// On Moodle 4.4+/Open LMS, a legacy callback triggers debugging() migration
// notices that Whoops converts into fatal ErrorExceptions and breaks all JS
// (nav clicks only append "#"). Use db/hooks.php only.
