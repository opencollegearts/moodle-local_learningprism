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
 * Backup support for local_learningprism (course-level).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course-level backup plugin for Learning Prism.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_local_learningprism_plugin extends backup_local_plugin {

    /**
     * Define course-level plugin structure.
     */
    protected function define_course_plugin_structure() {
        $userinfo = $this->get_setting_value('users');

        $plugin = $this->get_plugin_element();
        $wrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($wrapper);

        // Per-course enablement.
        $course = new backup_nested_element('learningprism_course', ['id'], [
            'courseid', 'enabled', 'allowstudentaudio', 'defaultvoice', 'timemodified',
        ]);
        $wrapper->add_child($course);
        $course->set_source_table('local_learningprism_course', [
            'courseid' => backup::VAR_COURSEID,
        ]);

        // Google Notebook (course-scoped; external IDs cleared on restore).
        $notebooks = new backup_nested_element('learningprism_notebooks');
        $notebook = new backup_nested_element('learningprism_notebook', ['id'], [
            'courseid', 'notebookid', 'name', 'url', 'sharemode', 'groupemail',
            'status', 'errorinfo', 'skipinfo', 'userid', 'timecreated', 'timemodified',
        ]);
        $nbsources = new backup_nested_element('learningprism_nbsources');
        $nbsource = new backup_nested_element('learningprism_nbsource', ['id'], [
            'notebookid', 'sourcetype', 'cmid', 'fileid', 'contenthash', 'gsourceid',
            'displayname', 'status', 'errorinfo', 'timemodified',
        ]);
        $wrapper->add_child($notebooks);
        $notebooks->add_child($notebook);
        $notebook->add_child($nbsources);
        $nbsources->add_child($nbsource);

        $notebook->set_source_table('local_learningprism_notebook', [
            'courseid' => backup::VAR_COURSEID,
        ]);
        $nbsource->set_source_table('local_learningprism_nbsource', [
            'notebookid' => backup::VAR_PARENTID,
        ]);
        $notebook->annotate_ids('user', 'userid');

        // Google Drive archive (course-scoped; external IDs cleared on restore).
        $archives = new backup_nested_element('learningprism_archives');
        $archive = new backup_nested_element('learningprism_archive', ['id'], [
            'courseid', 'folderid', 'url', 'sharemode', 'groupemail',
            'status', 'errorinfo', 'skipinfo', 'userid', 'timecreated', 'timemodified',
        ]);
        $archiveitems = new backup_nested_element('learningprism_archiveitems');
        $archiveitem = new backup_nested_element('learningprism_archiveitem', ['id'], [
            'archiveid', 'sectionnum', 'itemindex', 'sourcetype', 'cmid', 'fileid', 'format',
            'contenthash', 'drivefileid', 'displayname', 'sectionfolder', 'formatfolder',
            'status', 'errorinfo', 'timemodified',
        ]);
        $wrapper->add_child($archives);
        $archives->add_child($archive);
        $archive->add_child($archiveitems);
        $archiveitems->add_child($archiveitem);

        $archive->set_source_table('local_learningprism_archive', [
            'courseid' => backup::VAR_COURSEID,
        ]);
        $archiveitem->set_source_table('local_learningprism_archiveitem', [
            'archiveid' => backup::VAR_PARENTID,
        ]);
        $archive->annotate_ids('user', 'userid');

        // Conversion jobs and request audit (user data).
        if ($userinfo) {
            $jobs = new backup_nested_element('learningprism_jobs');
            $job = new backup_nested_element('learningprism_job', ['id'], [
                'cmid', 'courseid', 'contenthash', 'format', 'provider', 'status',
                'fileid', 'options', 'errorinfo', 'userid', 'personal', 'optionshash',
                'timecreated', 'timemodified',
            ]);
            $requests = new backup_nested_element('learningprism_requests');
            $request = new backup_nested_element('learningprism_request', ['id'], [
                'jobid', 'userid', 'type', 'timecreated',
            ]);
            $wrapper->add_child($jobs);
            $jobs->add_child($job);
            $job->add_child($requests);
            $requests->add_child($request);

            $job->set_source_table('local_learningprism_job', [
                'courseid' => backup::VAR_COURSEID,
            ]);
            $request->set_source_table('local_learningprism_request', [
                'jobid' => backup::VAR_PARENTID,
            ]);

            $job->annotate_ids('user', 'userid');
            $request->annotate_ids('user', 'userid');

            // Converted files live in the system context, itemid = job id.
            $job->annotate_files(
                'local_learningprism',
                'formats',
                'id',
                \context_system::instance()->id
            );
        }
    }
}
