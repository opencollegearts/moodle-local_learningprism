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

namespace local_learningprism;

use context_system;
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * File storage helpers for converted formats.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filemanager {
    /**
     * Store conversion output for a job.
     *
     * @param int $jobid
     * @param string $filename
     * @param string $mimetype
     * @param string $content
     * @return stored_file
     */
    public static function store_converted_file(int $jobid, string $filename, string $mimetype, string $content): stored_file {
        $fs = get_file_storage();
        $contextid = context_system::instance()->id;

        $fs->delete_area_files($contextid, manager::COMPONENT, manager::FILEAREA, $jobid);

        $record = [
            'contextid' => $contextid,
            'component' => manager::COMPONENT,
            'filearea' => manager::FILEAREA,
            'itemid' => $jobid,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => $mimetype,
        ];
        return $fs->create_file_from_string($record, $content);
    }

    /**
     * @param \stdClass $job
     * @return stored_file|null
     */
    public static function get_job_file(\stdClass $job): ?stored_file {
        $fs = get_file_storage();
        if (!empty($job->fileid)) {
            $file = $fs->get_file_by_id((int) $job->fileid);
            if ($file) {
                return $file;
            }
        }
        $files = $fs->get_area_files(
            context_system::instance()->id,
            manager::COMPONENT,
            manager::FILEAREA,
            (int) $job->id,
            'filename',
            false
        );
        $file = reset($files);
        return $file ?: null;
    }

    /**
     * Pluginfile URL for a ready job.
     *
     * @param \stdClass $job
     * @return \moodle_url|null
     */
    public static function get_download_url(\stdClass $job): ?\moodle_url {
        $file = self::get_job_file($job);
        if (!$file) {
            return null;
        }
        $modcontext = \context_module::instance($job->cmid);
        return \moodle_url::make_pluginfile_url(
            $modcontext->id,
            manager::COMPONENT,
            manager::FILEAREA,
            (int) $job->id,
            '/',
            $file->get_filename(),
            true
        );
    }

    /**
     * Delete files for a job.
     *
     * @param int $jobid
     */
    public static function delete_job_files(int $jobid): void {
        $fs = get_file_storage();
        $fs->delete_area_files(context_system::instance()->id, manager::COMPONENT, manager::FILEAREA, $jobid);
    }

    /**
     * Store a DOCX built for a Drive archive item.
     *
     * @param int $itemid Archive item id
     * @param string $filename
     * @param string $content Binary DOCX
     * @return stored_file
     */
    public static function store_archive_file(int $itemid, string $filename, string $content): stored_file {
        $fs = get_file_storage();
        $contextid = context_system::instance()->id;
        $fs->delete_area_files($contextid, manager::COMPONENT, manager::FILEAREA_ARCHIVE, $itemid);
        $record = [
            'contextid' => $contextid,
            'component' => manager::COMPONENT,
            'filearea' => manager::FILEAREA_ARCHIVE,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
            'mimetype' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
        return $fs->create_file_from_string($record, $content);
    }

    /**
     * @param int $itemid
     * @return stored_file|null
     */
    public static function get_archive_file(int $itemid): ?stored_file {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            context_system::instance()->id,
            manager::COMPONENT,
            manager::FILEAREA_ARCHIVE,
            $itemid,
            'filename',
            false
        );
        $file = reset($files);
        return $file ?: null;
    }

    /**
     * @param int $itemid
     */
    public static function delete_archive_files(int $itemid): void {
        $fs = get_file_storage();
        $fs->delete_area_files(context_system::instance()->id, manager::COMPONENT, manager::FILEAREA_ARCHIVE, $itemid);
    }
}
