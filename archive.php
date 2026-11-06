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
 * Course Google Drive archive generate and share page.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_learningprism\local\archive\settings;
use local_learningprism\local\archive\sync_manager;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

global $OUTPUT, $PAGE, $USER;

$courseid = required_param('id', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/learningprism:manage', $context);

if (!manager::plugin_enabled() || !manager::course_enabled($courseid)) {
    throw new moodle_exception('error:notenabled', 'local_learningprism');
}
if (!settings::enabled()) {
    throw new moodle_exception('error:archivenotconfigured', 'local_learningprism');
}

$PAGE->set_url(new moodle_url('/local/learningprism/archive.php', ['id' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('archive:heading', 'local_learningprism'));
$PAGE->set_heading($course->fullname);

$record = sync_manager::get_record($courseid);
$form = new \local_learningprism\form\archive_form();
$form->set_data([
    'id' => $courseid,
    'sharemode' => $record->sharemode ?? sync_manager::SHARE_GROUP,
    'groupemail' => $record->groupemail ?? '',
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}
if ($data = $form->get_data()) {
    $shareonly = !empty($data->updatesharing);
    if (!empty($data->buttonar) && is_array($data->buttonar)) {
        $shareonly = !empty($data->buttonar['updatesharing']);
    }
    sync_manager::queue(
        $courseid,
        (int) $USER->id,
        (string) $data->sharemode,
        (string) ($data->groupemail ?? ''),
        $shareonly
    );
    $message = $shareonly
        ? get_string('archive:sharequeued', 'local_learningprism')
        : get_string('archive:queued', 'local_learningprism');
    redirect(
        new moodle_url('/local/learningprism/archive.php', ['id' => $courseid]),
        $message,
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$record = sync_manager::get_record($courseid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('archive:heading', 'local_learningprism'));
echo $OUTPUT->box(get_string('archive:intro', 'local_learningprism'), 'generalbox');

if ($record) {
    $statuskey = 'archive:status:' . $record->status;
    $status = get_string_manager()->string_exists($statuskey, 'local_learningprism')
        ? get_string($statuskey, 'local_learningprism')
        : $record->status;
    echo html_writer::div(
        html_writer::tag('strong', get_string('archive:currentstatus', 'local_learningprism') . ': ') . s($status),
        'mb-2'
    );
    if (!empty($record->sharemode)) {
        $modekey = 'archive:sharemode_' . $record->sharemode;
        $modelabel = get_string_manager()->string_exists($modekey, 'local_learningprism')
            ? get_string($modekey, 'local_learningprism')
            : $record->sharemode;
        echo html_writer::div(
            html_writer::tag('strong', get_string('archive:sharemode', 'local_learningprism') . ': ') . s($modelabel),
            'mb-2'
        );
    }
    $archiveurl = manager::sanitise_external_url((string) ($record->url ?? ''));
    if ($archiveurl !== '' && ($record->status === sync_manager::STATUS_READY
            || has_capability('local/learningprism:viewarchive', $context))) {
        echo html_writer::div(
            html_writer::link($archiveurl, get_string('archive:open', 'local_learningprism'), [
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
            ]),
            'mb-2'
        );
    }
    if (!empty($record->errorinfo)) {
        echo $OUTPUT->notification(s($record->errorinfo), \core\output\notification::NOTIFY_ERROR);
    }
    $skipped = sync_manager::skipped_items($record);
    if ($skipped) {
        $items = [];
        foreach ($skipped as $skip) {
            $name = s($skip['name'] ?? '');
            $reason = s($skip['reason'] ?? '');
            $items[] = $name . ($reason !== '' ? ' — ' . $reason : '');
        }
        echo html_writer::tag('h3', get_string('archive:skipped', 'local_learningprism'));
        echo html_writer::alist($items);
    }
}

$form->display();
echo $OUTPUT->footer();
