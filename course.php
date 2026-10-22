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
 * Per-course Learning Prism enablement (fallback UI).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/learningprism:manage', $context);

$PAGE->set_url(new moodle_url('/local/learningprism/course.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_learningprism'));
$PAGE->set_heading($course->fullname);

$form = new \local_learningprism\form\course_enable_form(null, ['courseid' => $courseid]);
$form->set_data(['id' => $courseid, 'enabled' => manager::course_enabled($courseid) ? 1 : 0]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
if ($data = $form->get_data()) {
    manager::set_course_enabled($courseid, !empty($data->enabled));
    redirect(
        new moodle_url('/course/view.php', ['id' => $courseid]),
        get_string('changessaved'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('course:enable', 'local_learningprism'));
$form->display();
echo $OUTPUT->footer();
