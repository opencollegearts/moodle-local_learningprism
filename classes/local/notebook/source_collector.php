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

use local_learningprism\local\mod\module_factory;
use local_learningprism\manager;
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * Collect activity conversions and native course files for a notebook.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_collector {
    /** @var string[] extension => MIME */
    public const FILE_TYPES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
    ];

    /**
     * @param int $courseid
     * @return array{sources: array, skipped: array}
     */
    public static function collect(int $courseid): array {
        $sources = [];
        $skipped = [];
        $modinfo = get_fast_modinfo($courseid);
        $format = settings::source_format();
        $maxbytes = settings::max_bytes();

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }
            if (in_array($cm->modname, manager::supported_modules(), true)) {
                self::add_activity($cm, $format, $sources, $skipped);
            } else if ($cm->modname === 'resource' || $cm->modname === 'folder') {
                self::add_module_files($cm, $maxbytes, $sources, $skipped);
            }
        }

        if (count($sources) > settings::MAX_SOURCES) {
            $overflow = array_slice($sources, settings::MAX_SOURCES);
            $sources = array_slice($sources, 0, settings::MAX_SOURCES);
            foreach ($overflow as $item) {
                $skipped[] = [
                    'name' => $item['displayname'],
                    'reason' => get_string('notebook:skip:limit', 'local_learningprism'),
                ];
            }
        }

        return ['sources' => $sources, 'skipped' => $skipped];
    }

    /**
     * MIME type for a stored file if it is a supported notebook source.
     *
     * @param stored_file $file
     * @return string|null
     */
    public static function mime_for_file(stored_file $file): ?string {
        return self::mime_for_name($file->get_filename(), (string) $file->get_mimetype());
    }

    /**
     * MIME type for a filename/mimetype pair if it is a supported notebook source.
     *
     * @param string $filename
     * @param string $mimetype
     * @return string|null
     */
    public static function mime_for_name(string $filename, string $mimetype = ''): ?string {
        if ($filename === '.' || $filename === '') {
            return null;
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (isset(self::FILE_TYPES[$ext])) {
            return self::FILE_TYPES[$ext];
        }
        $mime = strtolower($mimetype);
        foreach (self::FILE_TYPES as $type) {
            if ($mime === $type) {
                return $type;
            }
        }
        if ($mime === 'audio/mp3') {
            return self::FILE_TYPES['mp3'];
        }
        return null;
    }

    /**
     * @param \cm_info $cm
     * @param string $format
     * @param array $sources
     * @param array $skipped
     */
    private static function add_activity($cm, string $format, array &$sources, array &$skipped): void {
        try {
            $module = module_factory::instance_from_cmid((int) $cm->id);
            $hash = $module->get_content_hash();
        } catch (\Throwable $e) {
            $skipped[] = [
                'name' => $cm->name,
                'reason' => $e->getMessage(),
            ];
            return;
        }
        $ext = $format === manager::FORMAT_PDF ? 'pdf' : 'docx';
        $mime = $format === manager::FORMAT_PDF
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $displayname = $cm->name . '.' . $ext;
        $sources[] = [
            'sourcetype' => 'activity',
            'cmid' => (int) $cm->id,
            'fileid' => 0,
            'contenthash' => $hash,
            'displayname' => $displayname,
            'mimetype' => $mime,
            'format' => $format,
        ];
    }

    /**
     * @param \cm_info $cm
     * @param int $maxbytes
     * @param array $sources
     * @param array $skipped
     */
    private static function add_module_files($cm, int $maxbytes, array &$sources, array &$skipped): void {
        $fs = get_file_storage();
        $context = \context_module::instance($cm->id);
        $component = $cm->modname === 'folder' ? 'mod_folder' : 'mod_resource';
        $files = $fs->get_area_files($context->id, $component, 'content', 0, 'filepath, filename', false);
        foreach ($files as $file) {
            $mime = self::mime_for_file($file);
            if ($mime === null) {
                continue;
            }
            if ($file->get_filesize() > $maxbytes) {
                $skipped[] = [
                    'name' => $file->get_filename(),
                    'reason' => get_string('notebook:skip:size', 'local_learningprism'),
                ];
                continue;
            }
            $sources[] = [
                'sourcetype' => 'file',
                'cmid' => (int) $cm->id,
                'fileid' => (int) $file->get_id(),
                'contenthash' => $file->get_contenthash(),
                'displayname' => $file->get_filename(),
                'mimetype' => $mime,
                'format' => '',
            ];
        }
    }
}
