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
 * Teacher pre-generate page.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learningprism\job_manager;
use local_learningprism\local\mod\module_factory;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

global $USER, $OUTPUT, $PAGE;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/learningprism:manage', $context);

if (!manager::plugin_enabled() || !manager::course_enabled($courseid)) {
    throw new moodle_exception('error:notenabled', 'local_learningprism');
}

$PAGE->set_url(new moodle_url('/local/learningprism/pregenerate.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('course:pregenerate', 'local_learningprism'));
$PAGE->set_heading($course->fullname);

if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
    $instances = module_factory::get_course_instances($courseid);
    $queued = 0;
    foreach ($instances as $instance) {
        $jobs = job_manager::queue_formats((int) $instance['cmid'], manager::enabled_formats(), (int) $USER->id);
        $queued += count($jobs);
    }
    redirect(
        new moodle_url('/local/learningprism/report.php', ['id' => $courseid]),
        get_string('modal:statusqueued', 'local_learningprism') . ' (' . $queued . ')',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('course:pregenerate', 'local_learningprism'));
echo $OUTPUT->confirm(
    get_string('modal:pregenerate', 'local_learningprism'),
    new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]),
    new moodle_url('/course/view.php', ['id' => $courseid])
);
echo $OUTPUT->footer();
