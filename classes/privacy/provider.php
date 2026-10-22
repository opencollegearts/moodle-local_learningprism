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

namespace local_learningprism\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\user_preference_provider {

    /**
     * Return metadata about stored personal data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_learningprism_job', [
            'userid' => 'privacy:metadata:local_learningprism_job:userid',
            'cmid' => 'privacy:metadata:local_learningprism_job:cmid',
            'format' => 'privacy:metadata:local_learningprism_job:format',
            'personal' => 'privacy:metadata:local_learningprism_job:personal',
            'timecreated' => 'privacy:metadata:local_learningprism_job:timecreated',
        ], 'privacy:metadata:local_learningprism_job');

        $collection->add_user_preference(
            'local_learningprism_printbackground',
            'privacy:metadata:preference:printbackground'
        );
        $collection->add_user_preference(
            'local_learningprism_font',
            'privacy:metadata:preference:font'
        );

        $collection->add_database_table('local_learningprism_request', [
            'userid' => 'privacy:metadata:local_learningprism_request:userid',
            'type' => 'privacy:metadata:local_learningprism_request:type',
            'timecreated' => 'privacy:metadata:local_learningprism_request:timecreated',
        ], 'privacy:metadata:local_learningprism_request');

        $collection->add_external_location_link('ttsprovider', [
            'text' => 'privacy:metadata:ttsprovider',
        ], 'privacy:metadata:ttsprovider');

        $collection->add_external_location_link('pdfengine', [
            'html' => 'privacy:metadata:pdfengine',
        ], 'privacy:metadata:pdfengine');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:corefiles');

        return $collection;
    }

    /**
     * Get contexts that contain user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {local_learningprism_job} j
                  JOIN {course_modules} cm ON cm.id = j.cmid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level
                 WHERE j.userid = :userid";
        $contextlist->add_from_sql($sql, ['userid' => $userid, 'level' => CONTEXT_MODULE]);

        $sql = "SELECT ctx.id
                  FROM {local_learningprism_request} r
                  JOIN {local_learningprism_job} j ON j.id = r.jobid
                  JOIN {course_modules} cm ON cm.id = j.cmid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level
                 WHERE r.userid = :userid";
        $contextlist->add_from_sql($sql, ['userid' => $userid, 'level' => CONTEXT_MODULE]);
        return $contextlist;
    }

    /**
     * Export user preferences.
     *
     * @param int $userid
     */
    public static function export_user_preferences(int $userid): void {
        $bg = get_user_preferences('local_learningprism_printbackground', null, $userid);
        if ($bg !== null && $bg !== '') {
            writer::export_user_preference(
                'local_learningprism',
                'local_learningprism_printbackground',
                $bg,
                get_string('privacy:metadata:preference:printbackground', 'local_learningprism')
            );
        }
        $font = get_user_preferences('local_learningprism_font', null, $userid);
        if ($font !== null && $font !== '') {
            writer::export_user_preference(
                'local_learningprism',
                'local_learningprism_font',
                $font,
                get_string('privacy:metadata:preference:font', 'local_learningprism')
            );
        }
    }

    /**
     * Export user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $cmid = $context->instanceid;
            $jobs = $DB->get_records('local_learningprism_job', ['cmid' => $cmid, 'userid' => $userid]);
            if ($jobs) {
                $path = [get_string('pluginname', 'local_learningprism')];
                writer::with_context($context)->export_data($path, (object) ['jobs' => array_values($jobs)]);
                foreach ($jobs as $job) {
                    if (empty($job->personal)) {
                        continue;
                    }
                    $file = \local_learningprism\filemanager::get_job_file($job);
                    if ($file) {
                        writer::with_context($context)->export_file($path, $file);
                    }
                }
            }
        }
    }

    /**
     * Delete all plugin data for a context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $jobs = $DB->get_records('local_learningprism_job', ['cmid' => $context->instanceid]);
        foreach ($jobs as $job) {
            $DB->delete_records('local_learningprism_request', ['jobid' => $job->id]);
            \local_learningprism\filemanager::delete_job_files((int) $job->id);
        }
        $DB->delete_records('local_learningprism_job', ['cmid' => $context->instanceid]);
    }

    /**
     * Delete a user's plugin data in the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        unset_user_preference('local_learningprism_printbackground', $userid);
        unset_user_preference('local_learningprism_font', $userid);
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }
            $jobs = $DB->get_records('local_learningprism_job', [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ]);
            foreach ($jobs as $job) {
                $DB->delete_records('local_learningprism_request', ['jobid' => $job->id]);
                if (!empty($job->personal)) {
                    \local_learningprism\filemanager::delete_job_files((int) $job->id);
                    $DB->delete_records('local_learningprism_job', ['id' => $job->id]);
                    continue;
                }
                // Keep shared conversion file; clear userid linkage.
                $job->userid = 0;
                $DB->update_record('local_learningprism_job', $job);
            }
            $jobids = $DB->get_fieldset_select(
                'local_learningprism_job',
                'id',
                'cmid = :cmid',
                ['cmid' => $context->instanceid]
            );
            if ($jobids) {
                list($jobinsql, $jobparams) = $DB->get_in_or_equal($jobids, SQL_PARAMS_NAMED, 'jobid');
                $DB->delete_records_select(
                    'local_learningprism_request',
                    "userid = :userid AND jobid $jobinsql",
                    array_merge(['userid' => $userid], $jobparams)
                );
            }
        }
    }

    /**
     * List users who have data in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $sql = "SELECT userid FROM {local_learningprism_job} WHERE cmid = :cmid AND userid > 0";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
        $sql = "SELECT r.userid
                  FROM {local_learningprism_request} r
                  JOIN {local_learningprism_job} j ON j.id = r.jobid
                 WHERE j.cmid = :cmid";
        $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
    }

    /**
     * Delete data for multiple users in a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['cmid'] = $context->instanceid;
        $jobids = $DB->get_fieldset_select(
            'local_learningprism_job',
            'id',
            'cmid = :cmid',
            ['cmid' => $context->instanceid]
        );
        if ($jobids) {
            list($jobinsql, $jobparams) = $DB->get_in_or_equal($jobids, SQL_PARAMS_NAMED, 'jid');
            $DB->delete_records_select(
                'local_learningprism_request',
                "userid $insql AND jobid $jobinsql",
                array_merge($params, $jobparams)
            );
        }
        $personaljobs = $DB->get_records_select(
            'local_learningprism_job',
            "cmid = :cmid AND personal = 1 AND userid $insql",
            $params
        );
        foreach ($personaljobs as $job) {
            \local_learningprism\filemanager::delete_job_files((int) $job->id);
            $DB->delete_records('local_learningprism_job', ['id' => $job->id]);
        }
        $DB->set_field_select(
            'local_learningprism_job',
            'userid',
            0,
            "cmid = :cmid AND personal = 0 AND userid $insql",
            $params
        );
    }
}
