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
 * Restore support for local_learningprism (course-level).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course-level restore plugin for Learning Prism.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_local_learningprism_plugin extends restore_local_plugin {

    /** @var array<int,int> Old job id => new job id (for fileid refresh). */
    protected $jobmap = [];

    /** @var array<int,stdClass> Jobs needing cmid remap after activities restore. */
    protected $pendingjobs = [];

    /** @var array<int,stdClass> Notebook sources needing cmid remap. */
    protected $pendingnbsources = [];

    /** @var array<int,stdClass> Archive items needing cmid remap. */
    protected $pendingarchiveitems = [];

    /**
     * Paths attached to the course element.
     *
     * @return restore_path_element[]
     */
    protected function define_course_plugin_structure() {
        $paths = [];

        $paths[] = new restore_path_element(
            'learningprism_course',
            $this->get_pathfor('/learningprism_course')
        );
        $paths[] = new restore_path_element(
            'learningprism_notebook',
            $this->get_pathfor('/learningprism_notebooks/learningprism_notebook')
        );
        $paths[] = new restore_path_element(
            'learningprism_nbsource',
            $this->get_pathfor('/learningprism_notebooks/learningprism_notebook/learningprism_nbsources/learningprism_nbsource')
        );
        $paths[] = new restore_path_element(
            'learningprism_archive',
            $this->get_pathfor('/learningprism_archives/learningprism_archive')
        );
        $paths[] = new restore_path_element(
            'learningprism_archiveitem',
            $this->get_pathfor(
                '/learningprism_archives/learningprism_archive/learningprism_archiveitems/learningprism_archiveitem'
            )
        );

        // Always register — elements are simply absent when the backup omitted user data.
        $paths[] = new restore_path_element(
            'learningprism_job',
            $this->get_pathfor('/learningprism_jobs/learningprism_job')
        );
        $paths[] = new restore_path_element(
            'learningprism_request',
            $this->get_pathfor('/learningprism_jobs/learningprism_job/learningprism_requests/learningprism_request')
        );

        return $paths;
    }

    /**
     * Restore course enablement.
     *
     * @param array $data
     */
    public function process_learningprism_course($data) {
        global $DB;

        $data = (object) $data;
        $data->courseid = $this->task->get_courseid();
        $data->timemodified = time();

        $existing = $DB->get_record('local_learningprism_course', ['courseid' => $data->courseid]);
        if ($existing) {
            $existing->enabled = (int) $data->enabled;
            $existing->allowstudentaudio = (int) ($data->allowstudentaudio ?? 0);
            $existing->defaultvoice = (string) ($data->defaultvoice ?? '');
            $existing->timemodified = $data->timemodified;
            $DB->update_record('local_learningprism_course', $existing);
            return;
        }

        unset($data->id);
        $data->allowstudentaudio = (int) ($data->allowstudentaudio ?? 0);
        $data->defaultvoice = (string) ($data->defaultvoice ?? '');
        $DB->insert_record('local_learningprism_course', $data);
    }

    /**
     * Restore notebook record (Google resource IDs cleared — regenerate on target).
     *
     * @param array $data
     */
    public function process_learningprism_notebook($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->courseid = $this->task->get_courseid();
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        // External Google notebook will not exist on the target site.
        $data->notebookid = '';
        $data->url = null;
        $data->status = '';
        $data->errorinfo = null;
        $data->skipinfo = null;
        $data->timemodified = time();

        $existing = $DB->get_record('local_learningprism_notebook', ['courseid' => $data->courseid]);
        if ($existing) {
            $existing->name = $data->name;
            $existing->sharemode = $data->sharemode;
            $existing->groupemail = $data->groupemail;
            $existing->userid = $data->userid;
            $existing->notebookid = '';
            $existing->url = null;
            $existing->status = '';
            $existing->errorinfo = null;
            $existing->skipinfo = null;
            $existing->timemodified = $data->timemodified;
            $DB->update_record('local_learningprism_notebook', $existing);
            $DB->delete_records('local_learningprism_nbsource', ['notebookid' => $existing->id]);
            $this->set_mapping('learningprism_notebook', $oldid, $existing->id);
            return;
        }

        unset($data->id);
        $newid = $DB->insert_record('local_learningprism_notebook', $data);
        $this->set_mapping('learningprism_notebook', $oldid, $newid);
    }

    /**
     * Restore notebook source row.
     *
     * @param array $data
     */
    public function process_learningprism_nbsource($data) {
        global $DB;

        $data = (object) $data;
        $data->notebookid = $this->get_mappingid('learningprism_notebook', $data->notebookid);
        if (!$data->notebookid) {
            return;
        }

        $data->gsourceid = '';
        $data->status = 'queued';
        $data->errorinfo = null;
        $data->timemodified = time();
        // cmid remapped in after_restore_course once activities exist.
        $oldcmid = (int) $data->cmid;
        $data->cmid = 0;

        unset($data->id);
        $newid = $DB->insert_record('local_learningprism_nbsource', $data);
        if ($oldcmid > 0) {
            $this->pendingnbsources[$newid] = (object) ['id' => $newid, 'oldcmid' => $oldcmid];
        }
    }

    /**
     * Restore archive record (Google folder IDs cleared — regenerate on target).
     *
     * @param array $data
     */
    public function process_learningprism_archive($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->courseid = $this->task->get_courseid();
        $data->userid = $this->get_mappingid('user', $data->userid, 0);
        $data->folderid = '';
        $data->url = null;
        $data->status = '';
        $data->errorinfo = null;
        $data->skipinfo = null;
        $data->timemodified = time();

        $existing = $DB->get_record('local_learningprism_archive', ['courseid' => $data->courseid]);
        if ($existing) {
            $existing->sharemode = $data->sharemode;
            $existing->groupemail = $data->groupemail;
            $existing->userid = $data->userid;
            $existing->folderid = '';
            $existing->url = null;
            $existing->status = '';
            $existing->errorinfo = null;
            $existing->skipinfo = null;
            $existing->timemodified = $data->timemodified;
            $DB->update_record('local_learningprism_archive', $existing);
            $DB->delete_records('local_learningprism_archiveitem', ['archiveid' => $existing->id]);
            $this->set_mapping('learningprism_archive', $oldid, $existing->id);
            return;
        }

        unset($data->id);
        $newid = $DB->insert_record('local_learningprism_archive', $data);
        $this->set_mapping('learningprism_archive', $oldid, $newid);
    }

    /**
     * Restore archive item row.
     *
     * @param array $data
     */
    public function process_learningprism_archiveitem($data) {
        global $DB;

        $data = (object) $data;
        $data->archiveid = $this->get_mappingid('learningprism_archive', $data->archiveid);
        if (!$data->archiveid) {
            return;
        }

        $data->drivefileid = '';
        $data->status = 'queued';
        $data->errorinfo = null;
        $data->format = (string) ($data->format ?? 'docx');
        $data->formatfolder = (string) ($data->formatfolder ?? 'DocX');
        $data->timemodified = time();
        $oldcmid = (int) $data->cmid;
        $data->cmid = 0;

        unset($data->id);
        $newid = $DB->insert_record('local_learningprism_archiveitem', $data);
        if ($oldcmid > 0) {
            $this->pendingarchiveitems[$newid] = (object) ['id' => $newid, 'oldcmid' => $oldcmid];
        }
    }

    /**
     * Restore a conversion job.
     *
     * @param array $data
     */
    public function process_learningprism_job($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $userid = $this->get_mappingid('user', $data->userid, 0);
        if (!empty($data->personal) && !$userid) {
            // Personal jobs require a mapped user.
            return;
        }

        $data->courseid = $this->task->get_courseid();
        $data->userid = $userid;
        $data->fileid = null; // Refreshed after files restore.
        $oldcmid = (int) $data->cmid;
        $data->cmid = 0;

        unset($data->id);
        $newid = $DB->insert_record('local_learningprism_job', $data);
        $this->jobmap[$oldid] = $newid;
        $this->set_mapping(
            'learningprism_job',
            $oldid,
            $newid,
            true,
            $this->task->get_old_system_contextid()
        );

        if ($oldcmid > 0) {
            $this->pendingjobs[$newid] = (object) ['id' => $newid, 'oldcmid' => $oldcmid];
        }
    }

    /**
     * Restore a request/download audit row.
     *
     * @param array $data
     */
    public function process_learningprism_request($data) {
        global $DB;

        $data = (object) $data;
        $data->jobid = $this->get_mappingid('learningprism_job', $data->jobid);
        if (!$data->jobid) {
            return;
        }

        $userid = $this->get_mappingid('user', $data->userid, 0);
        if (!$userid) {
            return;
        }
        $data->userid = $userid;

        unset($data->id);
        $DB->insert_record('local_learningprism_request', $data);
    }

    /**
     * Restore converted files once job mappings exist.
     */
    protected function after_execute_course() {
        $this->add_related_files(
            'local_learningprism',
            'formats',
            'learningprism_job',
            $this->task->get_old_system_contextid()
        );
    }

    /**
     * Remap course-module IDs and refresh job fileids after the full restore.
     */
    public function after_restore_course() {
        global $DB;

        $fs = get_file_storage();
        $syscontextid = \context_system::instance()->id;

        foreach ($this->pendingjobs as $pending) {
            $newcmid = $this->get_mappingid('course_module', $pending->oldcmid, 0);
            if ($newcmid) {
                $DB->set_field('local_learningprism_job', 'cmid', $newcmid, ['id' => $pending->id]);
                continue;
            }
            // Activity was not restored — drop the orphaned job and its files.
            $DB->delete_records('local_learningprism_request', ['jobid' => $pending->id]);
            $DB->delete_records('local_learningprism_job', ['id' => $pending->id]);
            $fs->delete_area_files($syscontextid, 'local_learningprism', 'formats', $pending->id);
            foreach ($this->jobmap as $oldjobid => $newjobid) {
                if ((int) $newjobid === (int) $pending->id) {
                    unset($this->jobmap[$oldjobid]);
                }
            }
        }

        foreach ($this->pendingnbsources as $pending) {
            $newcmid = $this->get_mappingid('course_module', $pending->oldcmid, 0);
            if ($newcmid) {
                $DB->set_field('local_learningprism_nbsource', 'cmid', $newcmid, ['id' => $pending->id]);
            } else {
                $DB->delete_records('local_learningprism_nbsource', ['id' => $pending->id]);
            }
        }

        foreach ($this->pendingarchiveitems as $pending) {
            $newcmid = $this->get_mappingid('course_module', $pending->oldcmid, 0);
            if ($newcmid) {
                $DB->set_field('local_learningprism_archiveitem', 'cmid', $newcmid, ['id' => $pending->id]);
            } else {
                $DB->delete_records('local_learningprism_archiveitem', ['id' => $pending->id]);
            }
        }

        // Point job.fileid at the restored stored_file.
        foreach ($this->jobmap as $newjobid) {
            $files = $fs->get_area_files(
                $syscontextid,
                'local_learningprism',
                'formats',
                $newjobid,
                'filename',
                false
            );
            $file = reset($files);
            if ($file) {
                $DB->set_field('local_learningprism_job', 'fileid', $file->get_id(), ['id' => $newjobid]);
            }
        }

        // Invalidate course-enabled cache for the restored course.
        try {
            $cache = \cache::make('local_learningprism', 'courseenabled');
            $cache->delete($this->task->get_courseid());
        } catch (\Throwable $e) {
            // Cache definition may be unavailable during some restores.
        }
    }
}
