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

namespace local_learningprism\local\archive;

use local_learningprism\filemanager;
use local_learningprism\job_manager;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Orchestrate course Drive archive create, wipe, and upload.
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

    public const ITEM_QUEUED = 'queued';
    public const ITEM_READY = 'ready';
    public const ITEM_ERROR = 'error';

    /** Share with an existing Google Group (viewer). */
    public const SHARE_GROUP = 'group';
    /** Share with a Google Group, creating it if missing. */
    public const SHARE_GROUP_CREATE = 'groupcreate';
    /** Share with each enrolled Moodle participant email. */
    public const SHARE_PARTICIPANTS = 'participants';
    /** Anyone with the link can view. */
    public const SHARE_ANYONE = 'anyone';

    /**
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function get_record(int $courseid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('local_learningprism_archive', ['courseid' => $courseid]);
        return $record ?: null;
    }

    /**
     * Staff-facing Drive folder URL when ready.
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
     * Normalise a share mode value.
     *
     * @param string $sharemode
     * @return string
     */
    public static function normalise_sharemode(string $sharemode): string {
        $allowed = [
            self::SHARE_GROUP,
            self::SHARE_GROUP_CREATE,
            self::SHARE_PARTICIPANTS,
            self::SHARE_ANYONE,
        ];
        return in_array($sharemode, $allowed, true) ? $sharemode : self::SHARE_GROUP;
    }

    /**
     * Queue a full archive rebuild or a share-only update.
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
        string $sharemode = self::SHARE_GROUP,
        string $groupemail = '',
        bool $shareonly = false
    ): \stdClass {
        global $DB;

        if (!settings::enabled()) {
            throw new \moodle_exception('error:archivenotconfigured', 'local_learningprism');
        }
        $sharemode = self::normalise_sharemode($sharemode);
        $groupemail = trim($groupemail);
        $now = time();
        $record = self::get_record($courseid);
        if (!$record) {
            if ($shareonly) {
                throw new \moodle_exception('error:archivenotcreated', 'local_learningprism');
            }
            $record = (object) [
                'courseid' => $courseid,
                'folderid' => '',
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
            $record->id = $DB->insert_record('local_learningprism_archive', $record);
            return $record;
        }

        $record->sharemode = $sharemode;
        $record->groupemail = $groupemail;
        $record->userid = $userid;
        $record->errorinfo = null;
        $record->timemodified = $now;

        if ($shareonly) {
            if (empty($record->folderid)) {
                throw new \moodle_exception('error:archivenotcreated', 'local_learningprism');
            }
            $record->status = self::STATUS_SHARING;
            $DB->update_record('local_learningprism_archive', $record);
            return $record;
        }

        // Force a full replace: clear item Drive ids so upload wipes the old folder.
        $items = $DB->get_records('local_learningprism_archiveitem', ['archiveid' => $record->id]);
        foreach ($items as $item) {
            $item->drivefileid = '';
            $item->status = self::ITEM_QUEUED;
            $item->errorinfo = null;
            $item->timemodified = $now;
            $DB->update_record('local_learningprism_archiveitem', $item);
        }
        $record->status = self::STATUS_QUEUED;
        $record->url = '';
        $DB->update_record('local_learningprism_archive', $record);
        return $record;
    }

    /**
     * Queue a share refresh when enrolments change (participants or managed Google Group).
     *
     * @param int $courseid
     * @return void
     */
    public static function queue_participant_share_refresh(int $courseid): void {
        global $DB;
        if (!settings::enabled()) {
            return;
        }
        $record = self::get_record($courseid);
        if (!$record || empty($record->folderid)) {
            return;
        }
        $mode = (string) ($record->sharemode ?? '');
        if ($mode !== self::SHARE_PARTICIPANTS && $mode !== self::SHARE_GROUP_CREATE) {
            return;
        }
        if (!in_array($record->status, [self::STATUS_READY, self::STATUS_SHARING, self::STATUS_ERROR], true)) {
            return;
        }
        $record->status = self::STATUS_SHARING;
        $record->errorinfo = null;
        $record->timemodified = time();
        $DB->update_record('local_learningprism_archive', $record);
    }

    /**
     * Process one archive step (up to googleuploadsperrun file uploads when uploading).
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
            'local_learningprism_archive',
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
            error_log('local_learningprism archive ' . (int) $record->id . ': ' . $detail);
            $fresh = self::get_record((int) $record->courseid) ?? $record;
            $fresh->status = self::STATUS_ERROR;
            $fresh->errorinfo = self::safe_error_text($detail);
            $fresh->timemodified = time();
            $DB->update_record('local_learningprism_archive', $fresh);
        }
        $fresh = self::get_record((int) $record->courseid) ?? $record;
        self::trace_record($fresh);
        return 1;
    }

    /**
     * @param \stdClass $record
     * @return array
     */
    public static function skipped_items(\stdClass $record): array {
        $decoded = json_decode((string) $record->skipinfo, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param string $detail
     * @return string
     */
    private static function safe_error_text(string $detail): string {
        $detail = trim($detail);
        if ($detail === '') {
            return get_string('error:archiveapi', 'local_learningprism');
        }
        if (\core_text::strlen($detail) > 500) {
            $detail = \core_text::substr($detail, 0, 500) . '...';
        }
        return $detail;
    }

    /**
     * @param \stdClass $record
     * @param int $uploadlimit Max Drive uploads in this run while status is uploading
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
        $total = $DB->count_records('local_learningprism_archiveitem', ['archiveid' => $record->id]);
        $ready = $DB->count_records('local_learningprism_archiveitem', [
            'archiveid' => $record->id,
            'status' => self::ITEM_READY,
        ]);
        $folder = $record->folderid !== '' ? $record->folderid : '(none)';
        mtrace('local_learningprism archive course=' . (int) $record->courseid .
            ' status=' . $record->status .
            ' items=' . $ready . '/' . $total .
            ' folder=' . $folder);
        if (!empty($record->errorinfo)) {
            mtrace('local_learningprism archive error: ' . $record->errorinfo);
        }
        if (!empty($record->url)) {
            mtrace('local_learningprism archive url: ' . $record->url);
        }
    }

    /**
     * @param \stdClass $record
     */
    private static function ensure_conversions(\stdClass $record): void {
        global $DB;

        $collected = collector::collect((int) $record->courseid);
        $record->skipinfo = json_encode($collected['skipped']);
        $waiting = false;
        $userid = (int) $record->userid;
        if ($userid <= 0) {
            $admin = get_admin();
            $userid = $admin ? (int) $admin->id : 2;
        }

        $wantedkeys = [];
        foreach ($collected['sources'] as $source) {
            $wantedkeys[self::item_key($source)] = true;
            self::upsert_item($record, $source);
            if ($source['sourcetype'] !== collector::TYPE_ACTIVITY) {
                continue;
            }
            $job = job_manager::ensure_shared_format((int) $source['cmid'], manager::FORMAT_DOCX, $userid);
            if (!$job) {
                $collected['skipped'][] = [
                    'name' => $source['displayname'],
                    'reason' => get_string('error:conversionfailed', 'local_learningprism'),
                ];
                self::mark_item_error($record, $source, get_string('error:conversionfailed', 'local_learningprism'));
                continue;
            }
            if ($job->status === manager::STATUS_ERROR) {
                $collected['skipped'][] = [
                    'name' => $source['displayname'],
                    'reason' => $job->errorinfo ?: get_string('error:conversionfailed', 'local_learningprism'),
                ];
                self::mark_item_error($record, $source, (string) $job->errorinfo);
                continue;
            }
            if ($job->status !== manager::STATUS_READY) {
                $waiting = true;
            }
        }

        // Drop items no longer in the course inventory.
        $rows = $DB->get_records('local_learningprism_archiveitem', ['archiveid' => $record->id]);
        foreach ($rows as $row) {
            $key = $row->sourcetype . ':' . $row->sectionnum . ':' . $row->cmid . ':' . $row->fileid;
            if (!isset($wantedkeys[$key])) {
                filemanager::delete_archive_files((int) $row->id);
                $DB->delete_records('local_learningprism_archiveitem', ['id' => $row->id]);
            }
        }

        $record->skipinfo = json_encode($collected['skipped']);
        $record->status = $waiting ? self::STATUS_CONVERTING : self::STATUS_UPLOADING;
        $record->timemodified = time();
        $DB->update_record('local_learningprism_archive', $record);
    }

    /**
     * Wipe previous Drive folder if needed, then upload one pending file.
     *
     * @param \stdClass $record
     */
    private static function upload_next(\stdClass $record): void {
        global $DB;

        $client = new drive_client();
        self::ensure_remote_root($record, $client);

        $collected = collector::collect((int) $record->courseid);
        $record->skipinfo = json_encode($collected['skipped']);
        $wanted = [];
        foreach ($collected['sources'] as $source) {
            $wanted[self::item_key($source)] = $source;
            self::upsert_item($record, $source);
        }

        $rows = $DB->get_records('local_learningprism_archiveitem', ['archiveid' => $record->id], 'sectionnum ASC, itemindex ASC');
        $pending = null;
        $pendingsource = null;
        foreach ($rows as $row) {
            $key = $row->sourcetype . ':' . $row->sectionnum . ':' . $row->cmid . ':' . $row->fileid;
            if (!isset($wanted[$key])) {
                continue;
            }
            $source = $wanted[$key];
            if ($row->status === self::ITEM_ERROR) {
                continue;
            }
            $needs = ($row->status !== self::ITEM_READY)
                || ($row->contenthash !== $source['contenthash'])
                || empty($row->drivefileid);
            if ($needs) {
                $pending = $row;
                $pendingsource = $source;
                break;
            }
        }

        if (!$pending) {
            $record->status = self::STATUS_SHARING;
            $record->errorinfo = null;
            $record->timemodified = time();
            $DB->update_record('local_learningprism_archive', $record);
            return;
        }

        self::upload_item($record, $pending, $pendingsource, $client);
        $record->timemodified = time();
        $DB->update_record('local_learningprism_archive', $record);
    }

    /**
     * Apply Drive sharing for the course archive root folder.
     *
     * @param \stdClass $record
     */
    private static function share(\stdClass $record): void {
        global $DB;

        if (empty($record->folderid)) {
            throw new \moodle_exception('error:archivenotcreated', 'local_learningprism');
        }

        $mode = self::normalise_sharemode((string) ($record->sharemode ?? self::SHARE_GROUP));
        $targets = self::share_targets($record, $mode);
        $client = new drive_client();
        $client->replace_share_permissions($record->folderid, $targets);

        $record->status = self::STATUS_READY;
        $record->errorinfo = null;
        $record->timemodified = time();
        $DB->update_record('local_learningprism_archive', $record);
    }

    /**
     * @param \stdClass $record
     * @param string $mode
     * @return array
     */
    private static function share_targets(\stdClass $record, string $mode): array {
        if ($mode === self::SHARE_ANYONE) {
            return [['type' => 'anyone', 'role' => 'reader']];
        }

        if ($mode === self::SHARE_PARTICIPANTS) {
            $emails = self::participant_emails((int) $record->courseid);
            if (!$emails) {
                throw new \moodle_exception('error:archivenoshare', 'local_learningprism');
            }
            $targets = [];
            foreach ($emails as $email) {
                $targets[] = [
                    'type' => 'user',
                    'email' => $email,
                    'role' => 'reader',
                ];
            }
            return $targets;
        }

        $email = trim((string) ($record->groupemail ?? ''));
        if ($email === '' || !validate_email($email)) {
            throw new \moodle_exception('error:archivegroupemail', 'local_learningprism');
        }

        if ($mode === self::SHARE_GROUP_CREATE) {
            $course = get_course((int) $record->courseid);
            $dir = new directory_client();
            $dir->ensure_group(
                $email,
                shorten_text(format_string($course->fullname), 60, false, ''),
                get_string('archive:groupdescription', 'local_learningprism', format_string($course->shortname))
            );
            // Apply (or re-apply) member-only post/view/contact settings on every share sync.
            $dir->apply_member_lockdown($email);
            // Keep group membership aligned with enrolled Moodle users (create + Update sharing).
            $dir->sync_members($email, self::participant_emails((int) $record->courseid));
        }

        return [[
            'type' => 'group',
            'email' => $email,
            'role' => 'reader',
        ]];
    }

    /**
     * Active enrolled user emails for participant sharing.
     *
     * @param int $courseid
     * @return string[]
     */
    public static function participant_emails(int $courseid): array {
        $context = \context_course::instance($courseid);
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
     * On each full rebuild, delete the previous folder and create a new root.
     *
     * @param \stdClass $record
     * @param drive_client $client
     */
    private static function ensure_remote_root(\stdClass $record, drive_client $client): void {
        global $DB;

        // Mid-upload: at least one file already has a Drive id in the new tree.
        $uploaded = $DB->count_records_select(
            'local_learningprism_archiveitem',
            'archiveid = :aid AND drivefileid <> :empty',
            ['aid' => $record->id, 'empty' => '']
        );
        if ($uploaded > 0 && $record->folderid !== '') {
            return;
        }

        if ($record->folderid !== '') {
            $client->delete($record->folderid);
        }

        // Reset all items for a clean upload pass.
        $rows = $DB->get_records('local_learningprism_archiveitem', ['archiveid' => $record->id]);
        foreach ($rows as $row) {
            $row->drivefileid = '';
            if ($row->status !== self::ITEM_ERROR) {
                $row->status = self::ITEM_QUEUED;
            }
            $row->timemodified = time();
            $DB->update_record('local_learningprism_archiveitem', $row);
        }

        $course = get_course((int) $record->courseid);
        $parent = settings::parent_folder_id();
        $created = $client->create_folder(settings::root_folder_name($course), $parent);
        $record->folderid = $created['id'];
        $record->url = $created['webViewLink'] ?? $client->web_view_link($record->folderid);
        $record->timemodified = time();
        $DB->update_record('local_learningprism_archive', $record);
    }

    /**
     * @param array $source
     * @return string
     */
    private static function item_key(array $source): string {
        return $source['sourcetype'] . ':' . $source['sectionnum'] . ':' . $source['cmid'] . ':' . $source['fileid'];
    }

    /**
     * @param \stdClass $record
     * @param array $source
     */
    private static function upsert_item(\stdClass $record, array $source): void {
        global $DB;
        $existing = $DB->get_record('local_learningprism_archiveitem', [
            'archiveid' => $record->id,
            'sourcetype' => $source['sourcetype'],
            'sectionnum' => $source['sectionnum'],
            'cmid' => $source['cmid'],
            'fileid' => $source['fileid'],
        ]);
        $now = time();
        $displayname = clean_filename($source['displayname']);
        if ($existing) {
            if ($existing->contenthash !== $source['contenthash'] || empty($existing->drivefileid)) {
                $existing->status = self::ITEM_QUEUED;
                $existing->drivefileid = '';
            }
            $existing->itemindex = (int) $source['itemindex'];
            $existing->displayname = $displayname;
            $existing->sectionfolder = clean_filename($source['sectionfolder']);
            $existing->contenthash = $source['contenthash'];
            $existing->timemodified = $now;
            $DB->update_record('local_learningprism_archiveitem', $existing);
            return;
        }
        $DB->insert_record('local_learningprism_archiveitem', (object) [
            'archiveid' => $record->id,
            'sectionnum' => (int) $source['sectionnum'],
            'itemindex' => (int) $source['itemindex'],
            'sourcetype' => $source['sourcetype'],
            'cmid' => (int) $source['cmid'],
            'fileid' => (int) $source['fileid'],
            'contenthash' => $source['contenthash'],
            'drivefileid' => '',
            'displayname' => $displayname,
            'sectionfolder' => clean_filename($source['sectionfolder']),
            'status' => self::ITEM_QUEUED,
            'errorinfo' => null,
            'timemodified' => $now,
        ]);
    }

    /**
     * @param \stdClass $record
     * @param array $source
     * @param string $error
     */
    private static function mark_item_error(\stdClass $record, array $source, string $error): void {
        global $DB;
        $existing = $DB->get_record('local_learningprism_archiveitem', [
            'archiveid' => $record->id,
            'sourcetype' => $source['sourcetype'],
            'sectionnum' => $source['sectionnum'],
            'cmid' => $source['cmid'],
            'fileid' => $source['fileid'],
        ]);
        if (!$existing) {
            return;
        }
        $existing->status = self::ITEM_ERROR;
        $existing->errorinfo = $error;
        $existing->timemodified = time();
        $DB->update_record('local_learningprism_archiveitem', $existing);
    }

    /**
     * @param \stdClass $record
     * @param \stdClass $row
     * @param array $source
     * @param drive_client $client
     */
    private static function upload_item(\stdClass $record, \stdClass $row, array $source, drive_client $client): void {
        global $DB;

        $sectionfolderid = $client->ensure_folder($row->sectionfolder, $record->folderid);
        $stored = self::local_file($row, $source, (int) $record->courseid);
        if (!$stored) {
            $row->status = self::ITEM_ERROR;
            $row->errorinfo = get_string('modal:nofile', 'local_learningprism');
            $row->timemodified = time();
            $DB->update_record('local_learningprism_archiveitem', $row);
            return;
        }

        $dir = make_request_directory();
        $tmp = $dir . '/' . clean_filename($source['displayname']);
        $stored->copy_content_to($tmp);
        $uploaded = $client->upload_file(
            $tmp,
            $source['displayname'],
            $source['mimetype'],
            $sectionfolderid
        );
        $row->drivefileid = $uploaded['id'];
        $row->contenthash = $source['contenthash'];
        $row->displayname = clean_filename($source['displayname']);
        $row->sectionfolder = clean_filename($source['sectionfolder']);
        $row->itemindex = (int) $source['itemindex'];
        $row->status = self::ITEM_READY;
        $row->errorinfo = null;
        $row->timemodified = time();
        $DB->update_record('local_learningprism_archiveitem', $row);
    }

    /**
     * @param \stdClass $row
     * @param array $source
     * @param int $courseid
     * @return \stored_file|null
     */
    private static function local_file(\stdClass $row, array $source, int $courseid): ?\stored_file {
        global $DB;

        if ($source['sourcetype'] === collector::TYPE_FILE) {
            $fs = get_file_storage();
            $file = $fs->get_file_by_id((int) $source['fileid']);
            return $file ?: null;
        }

        if ($source['sourcetype'] === collector::TYPE_ACTIVITY) {
            $job = $DB->get_record('local_learningprism_job', [
                'cmid' => $source['cmid'],
                'contenthash' => $source['contenthash'],
                'format' => manager::FORMAT_DOCX,
                'personal' => 0,
                'status' => manager::STATUS_READY,
            ]);
            if (!$job) {
                $jobs = $DB->get_records('local_learningprism_job', [
                    'cmid' => $source['cmid'],
                    'format' => manager::FORMAT_DOCX,
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

        $course = get_course($courseid);
        return docx_builder::ensure_stored($row, $source, $course);
    }
}
