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

namespace local_learningprism\local\notebook;

use local_learningprism\filemanager;
use local_learningprism\job_manager;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Orchestrate course notebook create, source upload, and sharing.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_manager {
    public const STATUS_QUEUED = 'queued';
    public const STATUS_CONVERTING = 'converting';
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_SHARING = 'sharing';
    public const STATUS_READY = 'ready';
    public const STATUS_ERROR = 'error';

    public const SHARE_GROUP = 'group';
    public const SHARE_PARTICIPANTS = 'participants';

    public const SOURCE_QUEUED = 'queued';
    public const SOURCE_READY = 'ready';
    public const SOURCE_ERROR = 'error';

    /**
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function get_record(int $courseid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_learningprism_notebook', ['courseid' => $courseid]);
        return $record ?: null;
    }

    /**
     * Student-facing URL when the notebook is ready.
     *
     * @param int $courseid
     * @return string
     */
    public static function public_url(int $courseid): string {
        if (!settings::enabled()) {
            return '';
        }
        $record = self::get_record($courseid);
        if (!$record || $record->status !== self::STATUS_READY || empty($record->url)) {
            return '';
        }
        return \local_learningprism\manager::sanitise_external_url((string) $record->url);
    }

    /**
     * Queue a full sync or a share-only update.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $sharemode
     * @param string $groupemail
     * @param bool $shareonly
     * @return \stdClass
     */
    public static function queue(
        int $courseid,
        int $userid,
        string $sharemode,
        string $groupemail,
        bool $shareonly = false
    ): \stdClass {
        global $DB;

        if (!settings::enabled()) {
            throw new \moodle_exception('error:notebooknotconfigured', 'local_learningprism');
        }
        if ($sharemode !== self::SHARE_PARTICIPANTS) {
            $sharemode = self::SHARE_GROUP;
        }
        $groupemail = trim($groupemail);
        $now = time();
        $record = self::get_record($courseid);
        if (!$record) {
            $record = (object) [
                'courseid' => $courseid,
                'notebookid' => '',
                'name' => '',
                'url' => '',
                'sharemode' => $sharemode,
                'groupemail' => $groupemail,
                'status' => self::STATUS_QUEUED,
                'errorinfo' => null,
                'skipinfo' => null,
                'userid' => $userid,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('local_learningprism_notebook', $record);
        }

        if ($shareonly) {
            if (empty($record->notebookid)) {
                throw new \moodle_exception('error:notebooknotcreated', 'local_learningprism');
            }
            $record->status = self::STATUS_SHARING;
        } else {
            $record->status = self::STATUS_QUEUED;
        }
        $record->sharemode = $sharemode;
        $record->groupemail = $groupemail;
        $record->userid = $userid;
        $record->errorinfo = null;
        $record->timemodified = $now;
        $DB->update_record('local_learningprism_notebook', $record);
        return $record;
    }

    /**
     * Process one notebook step (up to googleuploadsperrun uploads when uploading).
     *
     * @return int 1 if work was done
     */
    public static function process_one(): int {
        global $DB;

        if (!settings::enabled()) {
            return 0;
        }

        list($insql, $params) = $DB->get_in_or_equal([
            self::STATUS_QUEUED,
            self::STATUS_CONVERTING,
            self::STATUS_UPLOADING,
            self::STATUS_SHARING,
        ], SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            'local_learningprism_notebook',
            "status $insql",
            $params,
            'timemodified ASC',
            '*',
            0,
            1
        );
        if (!$records) {
            return 0;
        }
        $record = reset($records);
        try {
            self::process_record($record, settings::uploads_per_run());
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
            if ($e instanceof \moodle_exception && !empty($e->debuginfo)) {
                $detail = $e->debuginfo;
            }
            error_log('local_learningprism notebook ' . (int) $record->id . ': ' . $detail);
            $fresh = self::get_record((int) $record->courseid) ?? $record;
            $fresh->status = self::STATUS_ERROR;
            $fresh->errorinfo = self::safe_error_text($detail);
            $fresh->timemodified = time();
            $DB->update_record('local_learningprism_notebook', $fresh);
        }
        $fresh = self::get_record((int) $record->courseid) ?? $record;
        self::trace_record($fresh);
        return 1;
    }

    /**
     * Course-page banner data (teachers always; students when the notebook is ready).
     *
     * @param int $courseid
     * @param bool $canmanage
     * @return array
     */
    public static function course_ui(int $courseid, bool $canmanage): array {
        $data = [
            'show' => false,
            'url' => '',
            'status' => '',
            'statuslabel' => '',
            'manageurl' => '',
            'managelabel' => get_string('notebook:manage', 'local_learningprism'),
            'openlabel' => get_string('notebook:open', 'local_learningprism'),
            'heading' => get_string('notebook:heading', 'local_learningprism'),
        ];
        if (!settings::enabled()) {
            return $data;
        }
        if ($canmanage) {
            $data['show'] = true;
            $data['manageurl'] = (new \moodle_url('/local/learningprism/notebook.php', ['id' => $courseid]))->out(false);
        }
        $record = self::get_record($courseid);
        if ($record) {
            $data['status'] = (string) $record->status;
            $statuskey = 'notebook:status:' . $record->status;
            $data['statuslabel'] = get_string_manager()->string_exists($statuskey, 'local_learningprism')
                ? get_string($statuskey, 'local_learningprism')
                : (string) $record->status;
            if (!empty($record->url) && ($record->status === self::STATUS_READY || $canmanage)) {
                $data['url'] = (string) $record->url;
            }
            if ($record->status === self::STATUS_READY && $data['url'] !== '') {
                $data['show'] = true;
            }
        }
        return $data;
    }

    /**
     * Teacher-facing API error (no stack traces or long Google troubleshooter URLs).
     *
     * @param string $detail
     * @return string
     */
    private static function safe_error_text(string $detail): string {
        $detail = trim($detail);
        $detail = preg_replace('/\s*Remediate access with this Troubleshooter URL.*$/s', '', $detail) ?? $detail;
        if ($detail === '') {
            return get_string('error:notebookapi', 'local_learningprism');
        }
        if (\core_text::strlen($detail) > 500) {
            $detail = \core_text::substr($detail, 0, 500) . '...';
        }
        return $detail;
    }

    /**
     * @param \stdClass $record
     * @param int $uploadlimit Max Notebook source uploads in this run while status is uploading
     */
    private static function process_record(\stdClass $record, int $uploadlimit = 1): void {
        $uploadlimit = max(1, min(50, $uploadlimit));
        if ($record->status === self::STATUS_QUEUED || $record->status === self::STATUS_CONVERTING) {
            self::ensure_conversions($record);
            $record = self::get_record((int) $record->courseid) ?? $record;
        }
        if ($record->status === self::STATUS_UPLOADING) {
            for ($i = 0; $i < $uploadlimit; $i++) {
                self::upload_next($record);
                $record = self::get_record((int) $record->courseid) ?? $record;
                if ($record->status !== self::STATUS_UPLOADING) {
                    break;
                }
            }
            if ($record->status === self::STATUS_UPLOADING) {
                return;
            }
        }
        if ($record->status === self::STATUS_SHARING) {
            self::share($record);
        }
    }

    /**
     * @param \stdClass $record
     */
    private static function trace_record(\stdClass $record): void {
        global $DB;
        $total = $DB->count_records('local_learningprism_nbsource', ['notebookid' => $record->id]);
        $ready = $DB->count_records('local_learningprism_nbsource', [
            'notebookid' => $record->id,
            'status' => self::SOURCE_READY,
        ]);
        $googleid = $record->notebookid !== '' ? $record->notebookid : '(none)';
        mtrace('local_learningprism notebook course=' . (int) $record->courseid .
            ' status=' . $record->status .
            ' sources=' . $ready . '/' . $total .
            ' googleid=' . $googleid);
        if (!empty($record->errorinfo)) {
            mtrace('local_learningprism notebook error: ' . $record->errorinfo);
        }
        if (!empty($record->url)) {
            mtrace('local_learningprism notebook url: ' . $record->url);
        }
    }

    /**
     * Queue activity conversions and wait until they are ready.
     *
     * @param \stdClass $record
     */
    private static function ensure_conversions(\stdClass $record): void {
        global $DB;

        $collected = source_collector::collect((int) $record->courseid);
        $record->skipinfo = json_encode($collected['skipped']);
        $format = settings::source_format();
        $waiting = false;
        $userid = (int) $record->userid;
        if ($userid <= 0) {
            $admin = get_admin();
            $userid = $admin ? (int) $admin->id : 2;
        }

        foreach ($collected['sources'] as $source) {
            self::upsert_source($record, $source);
            if ($source['sourcetype'] !== 'activity') {
                continue;
            }
            $job = job_manager::ensure_shared_format((int) $source['cmid'], $format, $userid);
            if (!$job) {
                $collected['skipped'][] = [
                    'name' => $source['displayname'],
                    'reason' => get_string('error:conversionfailed', 'local_learningprism'),
                ];
                self::mark_source_error($record, $source, get_string('error:conversionfailed', 'local_learningprism'));
                continue;
            }
            if ($job->status === manager::STATUS_ERROR) {
                $collected['skipped'][] = [
                    'name' => $source['displayname'],
                    'reason' => $job->errorinfo ?: get_string('error:conversionfailed', 'local_learningprism'),
                ];
                self::mark_source_error($record, $source, (string) $job->errorinfo);
                continue;
            }
            if ($job->status !== manager::STATUS_READY) {
                $waiting = true;
            }
        }
        $record->skipinfo = json_encode($collected['skipped']);
        $record->status = $waiting ? self::STATUS_CONVERTING : self::STATUS_UPLOADING;
        $record->timemodified = time();
        $DB->update_record('local_learningprism_notebook', $record);
    }

    /**
     * Create the Google notebook if needed, drop removed sources, upload one file.
     *
     * @param \stdClass $record
     */
    private static function upload_next(\stdClass $record): void {
        global $DB;

        $client = new client();
        self::ensure_remote_notebook($record, $client);

        $collected = source_collector::collect((int) $record->courseid);
        $record->skipinfo = json_encode($collected['skipped']);
        $wanted = [];
        foreach ($collected['sources'] as $source) {
            $wanted[self::source_key($source)] = $source;
            self::upsert_source($record, $source);
        }
        self::delete_removed_sources($record, $client, $wanted);

        $rows = $DB->get_records('local_learningprism_nbsource', ['notebookid' => $record->id]);
        $pending = null;
        $pendingsource = null;
        foreach ($rows as $row) {
            $key = $row->sourcetype . ':' . $row->cmid . ':' . $row->fileid;
            if (!isset($wanted[$key])) {
                continue;
            }
            $source = $wanted[$key];
            if ($row->status === self::SOURCE_ERROR) {
                continue;
            }
            $needs = ($row->status !== self::SOURCE_READY)
                || ($row->contenthash !== $source['contenthash'])
                || empty($row->gsourceid);
            if ($needs) {
                $pending = $row;
                $pendingsource = $source;
                break;
            }
        }

        if (!$pending) {
            $record->status = self::STATUS_SHARING;
            $record->timemodified = time();
            $DB->update_record('local_learningprism_notebook', $record);
            return;
        }

        self::upload_source($record, $pending, $pendingsource, $client);
        $record->timemodified = time();
        $DB->update_record('local_learningprism_notebook', $record);
    }

    /**
     * @param \stdClass $record
     */
    private static function share(\stdClass $record): void {
        global $DB;

        $emails = self::share_emails($record);
        if (!$emails) {
            $record->status = self::STATUS_ERROR;
            $record->errorinfo = get_string('error:notebooknoshare', 'local_learningprism');
            $record->timemodified = time();
            $DB->update_record('local_learningprism_notebook', $record);
            return;
        }

        $roles = [];
        foreach ($emails as $email) {
            $roles[] = [
                'email' => $email,
                'role' => 'PROJECT_ROLE_READER',
            ];
        }
        $client = new client();
        $client->share($record->notebookid, $roles);
        $record->status = self::STATUS_READY;
        $record->errorinfo = null;
        $record->timemodified = time();
        $DB->update_record('local_learningprism_notebook', $record);
    }

    /**
     * @param \stdClass $record
     * @return string[]
     */
    public static function share_emails(\stdClass $record): array {
        if ($record->sharemode === self::SHARE_GROUP) {
            $email = trim((string) $record->groupemail);
            return validate_email($email) ? [$email] : [];
        }
        $context = \context_course::instance((int) $record->courseid);
        $users = get_enrolled_users($context, '', 0, 'u.id, u.email', null, 0, 0, true);
        $emails = [];
        foreach ($users as $user) {
            $email = trim((string) $user->email);
            if ($email === '' || !validate_email($email)) {
                continue;
            }
            $emails[strtolower($email)] = $email;
        }
        return array_values($emails);
    }

    /**
     * Decoded skip list for the teacher UI.
     *
     * @param \stdClass $record
     * @return array
     */
    public static function skipped_items(\stdClass $record): array {
        $decoded = json_decode((string) $record->skipinfo, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array $source
     * @return string
     */
    private static function source_key(array $source): string {
        return $source['sourcetype'] . ':' . $source['cmid'] . ':' . $source['fileid'];
    }

    /**
     * @param \stdClass $record
     * @param array $source
     */
    private static function upsert_source(\stdClass $record, array $source): void {
        global $DB;
        $existing = $DB->get_record('local_learningprism_nbsource', [
            'notebookid' => $record->id,
            'sourcetype' => $source['sourcetype'],
            'cmid' => $source['cmid'],
            'fileid' => $source['fileid'],
        ]);
        $now = time();
        if ($existing) {
            if ($existing->contenthash !== $source['contenthash'] || empty($existing->gsourceid)) {
                $existing->status = self::SOURCE_QUEUED;
            }
            $existing->displayname = clean_filename($source['displayname']);
            $existing->timemodified = $now;
            $DB->update_record('local_learningprism_nbsource', $existing);
            return;
        }
        $DB->insert_record('local_learningprism_nbsource', (object) [
            'notebookid' => $record->id,
            'sourcetype' => $source['sourcetype'],
            'cmid' => $source['cmid'],
            'fileid' => $source['fileid'],
            'contenthash' => $source['contenthash'],
            'gsourceid' => '',
            'displayname' => clean_filename($source['displayname']),
            'status' => self::SOURCE_QUEUED,
            'errorinfo' => null,
            'timemodified' => $now,
        ]);
    }

    /**
     * @param \stdClass $record
     * @param array $source
     * @param string $error
     */
    private static function mark_source_error(\stdClass $record, array $source, string $error): void {
        global $DB;
        $existing = $DB->get_record('local_learningprism_nbsource', [
            'notebookid' => $record->id,
            'sourcetype' => $source['sourcetype'],
            'cmid' => $source['cmid'],
            'fileid' => $source['fileid'],
        ]);
        if (!$existing) {
            return;
        }
        $existing->status = self::SOURCE_ERROR;
        $existing->errorinfo = $error;
        $existing->timemodified = time();
        $DB->update_record('local_learningprism_nbsource', $existing);
    }

    /**
     * @param \stdClass $record
     * @param client $client
     * @param array $wanted
     */
    private static function delete_removed_sources(\stdClass $record, client $client, array $wanted): void {
        global $DB;
        $rows = $DB->get_records('local_learningprism_nbsource', ['notebookid' => $record->id]);
        $names = [];
        $deleteids = [];
        foreach ($rows as $row) {
            $key = $row->sourcetype . ':' . $row->cmid . ':' . $row->fileid;
            if (isset($wanted[$key])) {
                continue;
            }
            if ($row->gsourceid !== '') {
                $names[] = $client->source_name($record->notebookid, $row->gsourceid);
            }
            $deleteids[] = $row->id;
        }
        if ($names) {
            $client->batch_delete_sources($record->notebookid, $names);
        }
        foreach ($deleteids as $id) {
            $DB->delete_records('local_learningprism_nbsource', ['id' => $id]);
        }
    }

    /**
     * @param \stdClass $record
     * @param client $client
     */
    private static function ensure_remote_notebook(\stdClass $record, client $client): void {
        global $DB;
        if (!empty($record->notebookid)) {
            try {
                $client->get($record->notebookid);
                return;
            } catch (\Throwable $e) {
                error_log('local_learningprism notebook missing remotely: ' . $e->getMessage());
                $record->notebookid = '';
            }
        }
        $course = get_course((int) $record->courseid);
        $title = shorten_text(format_string($course->fullname), 100, false, '');
        $created = $client->create($title);
        $record->notebookid = $created['notebookId'];
        $record->name = $created['name'] ?? '';
        $record->url = settings::notebook_url($record->notebookid);
        $record->timemodified = time();
        $DB->update_record('local_learningprism_notebook', $record);
    }

    /**
     * @param \stdClass $record
     * @param \stdClass $row
     * @param array $source
     * @param client $client
     */
    private static function upload_source(\stdClass $record, \stdClass $row, array $source, client $client): void {
        global $DB;

        if (!empty($row->gsourceid) && $row->contenthash !== $source['contenthash']) {
            try {
                $client->batch_delete_sources($record->notebookid, [
                    $client->source_name($record->notebookid, $row->gsourceid),
                ]);
            } catch (\Throwable $e) {
                error_log('local_learningprism notebook delete source: ' . $e->getMessage());
            }
            $row->gsourceid = '';
        }

        $stored = self::local_file($source);
        if (!$stored) {
            $row->status = self::SOURCE_ERROR;
            $row->errorinfo = get_string('modal:nofile', 'local_learningprism');
            $row->timemodified = time();
            $DB->update_record('local_learningprism_nbsource', $row);
            return;
        }

        $dir = make_request_directory();
        $tmp = $dir . '/' . clean_filename($source['displayname']);
        $stored->copy_content_to($tmp);
        $uploaded = $client->upload_file(
            $record->notebookid,
            $tmp,
            $source['displayname'],
            $source['mimetype']
        );
        $row->gsourceid = $uploaded['sourceId']['id'];
        $row->contenthash = $source['contenthash'];
        $row->displayname = clean_filename($source['displayname']);
        $row->status = self::SOURCE_READY;
        $row->errorinfo = null;
        $row->timemodified = time();
        $DB->update_record('local_learningprism_nbsource', $row);
    }

    /**
     * @param array $source
     * @return \stored_file|null
     */
    private static function local_file(array $source): ?\stored_file {
        global $DB;
        if ($source['sourcetype'] === 'file') {
            $fs = get_file_storage();
            $file = $fs->get_file_by_id((int) $source['fileid']);
            return $file ?: null;
        }
        $job = $DB->get_record('local_learningprism_job', [
            'cmid' => $source['cmid'],
            'contenthash' => $source['contenthash'],
            'format' => $source['format'],
            'personal' => 0,
            'status' => manager::STATUS_READY,
        ]);
        if (!$job) {
            $jobs = $DB->get_records('local_learningprism_job', [
                'cmid' => $source['cmid'],
                'format' => $source['format'],
                'personal' => 0,
                'status' => manager::STATUS_READY,
            ], 'timemodified DESC', '*', 0, 1);
            $job = $jobs ? reset($jobs) : false;
        }
        if (!$job) {
            return null;
        }
        return filemanager::get_job_file($job);
    }
}
