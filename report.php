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
 * Course job report.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learningprism\job_manager;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

global $DB, $OUTPUT, $PAGE, $USER;

$courseid = required_param('id', PARAM_INT);
$reprocess = optional_param('reprocess', 0, PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/learningprism:viewreport', $context);

$PAGE->set_url(new moodle_url('/local/learningprism/report.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('report:heading', 'local_learningprism'));
$PAGE->set_heading($course->fullname);

$canmanage = has_capability('local/learningprism:manage', $context);

if ($reprocess && confirm_sesskey() && $canmanage) {
    $job = job_manager::get_job($reprocess);
    if ($job && (int) $job->courseid === $courseid) {
        job_manager::requeue_job($job, (int) $USER->id, true);
        redirect($PAGE->url, get_string('report:requeued', 'local_learningprism'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
}

$jobs = $DB->get_records('local_learningprism_job', ['courseid' => $courseid, 'personal' => 0], 'timemodified DESC', '*', 0, 200);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report:heading', 'local_learningprism'));

if (!$jobs) {
    echo $OUTPUT->notification(get_string('report:empty', 'local_learningprism'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('report:activity', 'local_learningprism'),
        get_string('report:format', 'local_learningprism'),
        get_string('report:status', 'local_learningprism'),
        get_string('report:error', 'local_learningprism'),
        get_string('report:timemodified', 'local_learningprism'),
        get_string('report:actions', 'local_learningprism'),
    ];
    foreach ($jobs as $job) {
        $cm = get_coursemodule_from_id(null, $job->cmid, $courseid, false, IGNORE_MISSING);
        $name = $cm ? format_string($cm->name) : ('#' . $job->cmid);
        $actions = '';
        $canrequeue = in_array($job->status, [manager::STATUS_ERROR, manager::STATUS_READY], true);
        if ($canrequeue && $canmanage) {
            $url = new moodle_url($PAGE->url, ['reprocess' => $job->id, 'sesskey' => sesskey()]);
            $actions = html_writer::link($url, get_string('report:reprocess', 'local_learningprism'));
        }
        $error = '';
        if (!empty($job->errorinfo)) {
            $error = html_writer::span(s($job->errorinfo), 'text-danger small');
        }
        $table->data[] = [
            $name,
            get_string('format:' . $job->format, 'local_learningprism'),
            get_string('status:' . $job->status, 'local_learningprism'),
            $error,
            userdate($job->timemodified),
            $actions,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
