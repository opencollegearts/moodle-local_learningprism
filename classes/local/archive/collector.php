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

use local_learningprism\html_normalizer;
use local_learningprism\local\converter\docx_converter;
use local_learningprism\local\mod\module_factory;
use local_learningprism\manager;
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * Collect section-indexed archive items for a course.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collector {
    /** PDF and DOCX only (no audio/video). */
    public const FILE_TYPES = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public const TYPE_SECTION = 'section';
    public const TYPE_ACTIVITY = 'activity';
    public const TYPE_ASSIGN = 'assign';
    public const TYPE_FORUM = 'forum';
    public const TYPE_FILE = 'file';

    /**
     * @param int $courseid
     * @return array{sources: array, skipped: array, sections: array}
     */
    public static function collect(int $courseid): array {
        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course);
        $maxbytes = settings::max_bytes();
        $sources = [];
        $skipped = [];
        $sectionsmeta = [];
        $sectionindex = 0;

        foreach ($modinfo->get_section_info_all() as $section) {
            if (!$section->uservisible) {
                continue;
            }
            $cmids = $modinfo->get_sections()[$section->section] ?? [];
            $sectionitems = [];
            $itemindex = 0;

            $sectionname = get_section_name($course, $section);
            $summaryhtml = self::section_summary_html($course, $section);
            if ($summaryhtml !== '') {
                $itemindex++;
                $basename = self::indexed_name($itemindex, get_string('archive:sectionoverview', 'local_learningprism'));
                $sectionitems[] = [
                    'sourcetype' => self::TYPE_SECTION,
                    'sectionnum' => (int) $section->section,
                    'itemindex' => $itemindex,
                    'cmid' => 0,
                    'fileid' => 0,
                    'contenthash' => html_normalizer::content_hash($summaryhtml),
                    'displayname' => $basename . '.docx',
                    'mimetype' => self::FILE_TYPES['docx'],
                    'format' => manager::FORMAT_DOCX,
                ];
            }

            foreach ($cmids as $cmid) {
                $cm = $modinfo->cms[$cmid] ?? null;
                if (!$cm || !$cm->uservisible) {
                    continue;
                }
                if (in_array($cm->modname, manager::supported_modules(), true)) {
                    $added = self::add_activity($cm, $itemindex, $section->section, $sectionitems, $skipped);
                    if ($added) {
                        $itemindex = $added;
                    }
                } else if ($cm->modname === 'assign') {
                    $added = self::add_assign($cm, $itemindex, $section->section, $sectionitems, $skipped);
                    if ($added) {
                        $itemindex = $added;
                    }
                } else if ($cm->modname === 'forum') {
                    $added = self::add_forum($cm, $itemindex, $section->section, $sectionitems, $skipped);
                    if ($added) {
                        $itemindex = $added;
                    }
                } else if ($cm->modname === 'resource' || $cm->modname === 'folder') {
                    $itemindex = self::add_module_files(
                        $cm,
                        $itemindex,
                        (int) $section->section,
                        $maxbytes,
                        $sectionitems,
                        $skipped
                    );
                }
            }

            if (!$sectionitems) {
                continue;
            }

            $sectionindex++;
            $foldername = self::indexed_name($sectionindex, $sectionname);
            $sectionsmeta[(int) $section->section] = [
                'index' => $sectionindex,
                'name' => $sectionname,
                'folder' => $foldername,
            ];
            foreach ($sectionitems as $item) {
                $item['sectionfolder'] = $foldername;
                $sources[] = $item;
            }
        }

        return ['sources' => $sources, 'skipped' => $skipped, 'sections' => $sectionsmeta];
    }

    /**
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
        return null;
    }

    /**
     * @param stored_file $file
     * @return string|null
     */
    public static function mime_for_file(stored_file $file): ?string {
        return self::mime_for_name($file->get_filename(), (string) $file->get_mimetype());
    }

    /**
     * Zero-padded index prefix for Drive sort order.
     *
     * @param int $index
     * @param string $name
     * @return string
     */
    public static function indexed_name(int $index, string $name): string {
        $safe = docx_converter::safe_filename($name);
        return sprintf('%02d_%s', $index, $safe);
    }

    /**
     * @param \stdClass $course
     * @param \section_info $section
     * @return string Normalised HTML or empty
     */
    public static function section_summary_html(\stdClass $course, $section): string {
        $summary = trim((string) $section->summary);
        if ($summary === '' || trim(html_to_text($summary, 0, false)) === '') {
            return '';
        }
        $context = \context_course::instance($course->id);
        $summary = file_rewrite_pluginfile_urls(
            $summary,
            'pluginfile.php',
            $context->id,
            'course',
            'section',
            $section->id
        );
        $body = format_text($summary, $section->summaryformat, ['context' => $context, 'noclean' => true]);
        $title = get_section_name($course, $section);
        $lang = $course->lang ?: current_language();
        return html_normalizer::normalise($title, $body, $lang);
    }

    /**
     * @param \cm_info $cm
     * @param int $itemindex
     * @param int $sectionnum
     * @param array $sectionitems
     * @param array $skipped
     * @return int|false New item index or false
     */
    private static function add_activity($cm, int $itemindex, int $sectionnum, array &$sectionitems, array &$skipped) {
        try {
            $module = module_factory::instance_from_cmid((int) $cm->id);
            $hash = $module->get_content_hash();
        } catch (\Throwable $e) {
            $skipped[] = [
                'name' => $cm->name,
                'reason' => $e->getMessage(),
            ];
            return false;
        }
        $itemindex++;
        $basename = self::indexed_name($itemindex, $cm->name);
        $sectionitems[] = [
            'sourcetype' => self::TYPE_ACTIVITY,
            'sectionnum' => $sectionnum,
            'itemindex' => $itemindex,
            'cmid' => (int) $cm->id,
            'fileid' => 0,
            'contenthash' => $hash,
            'displayname' => $basename . '.docx',
            'mimetype' => self::FILE_TYPES['docx'],
            'format' => manager::FORMAT_DOCX,
        ];
        return $itemindex;
    }

    /**
     * @param \cm_info $cm
     * @param int $itemindex
     * @param int $sectionnum
     * @param array $sectionitems
     * @param array $skipped
     * @return int|false
     */
    private static function add_assign($cm, int $itemindex, int $sectionnum, array &$sectionitems, array &$skipped) {
        try {
            $html = docx_builder::assign_html($cm);
            $hash = html_normalizer::content_hash($html);
        } catch (\Throwable $e) {
            $skipped[] = [
                'name' => $cm->name,
                'reason' => $e->getMessage(),
            ];
            return false;
        }
        $itemindex++;
        $basename = self::indexed_name($itemindex, $cm->name);
        $sectionitems[] = [
            'sourcetype' => self::TYPE_ASSIGN,
            'sectionnum' => $sectionnum,
            'itemindex' => $itemindex,
            'cmid' => (int) $cm->id,
            'fileid' => 0,
            'contenthash' => $hash,
            'displayname' => $basename . '.docx',
            'mimetype' => self::FILE_TYPES['docx'],
            'format' => manager::FORMAT_DOCX,
        ];
        return $itemindex;
    }

    /**
     * @param \cm_info $cm
     * @param int $itemindex
     * @param int $sectionnum
     * @param array $sectionitems
     * @param array $skipped
     * @return int|false
     */
    private static function add_forum($cm, int $itemindex, int $sectionnum, array &$sectionitems, array &$skipped) {
        try {
            $html = docx_builder::forum_html($cm);
            $hash = html_normalizer::content_hash($html);
        } catch (\Throwable $e) {
            $skipped[] = [
                'name' => $cm->name,
                'reason' => $e->getMessage(),
            ];
            return false;
        }
        $itemindex++;
        $basename = self::indexed_name($itemindex, $cm->name);
        $sectionitems[] = [
            'sourcetype' => self::TYPE_FORUM,
            'sectionnum' => $sectionnum,
            'itemindex' => $itemindex,
            'cmid' => (int) $cm->id,
            'fileid' => 0,
            'contenthash' => $hash,
            'displayname' => $basename . '.docx',
            'mimetype' => self::FILE_TYPES['docx'],
            'format' => manager::FORMAT_DOCX,
        ];
        return $itemindex;
    }

    /**
     * @param \cm_info $cm
     * @param int $itemindex
     * @param int $sectionnum
     * @param int $maxbytes
     * @param array $sectionitems
     * @param array $skipped
     * @return int Updated item index
     */
    private static function add_module_files(
        $cm,
        int $itemindex,
        int $sectionnum,
        int $maxbytes,
        array &$sectionitems,
        array &$skipped
    ): int {
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
                    'reason' => get_string('archive:skip:size', 'local_learningprism'),
                ];
                continue;
            }
            $itemindex++;
            $basename = self::indexed_name($itemindex, pathinfo($file->get_filename(), PATHINFO_FILENAME));
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            $sectionitems[] = [
                'sourcetype' => self::TYPE_FILE,
                'sectionnum' => $sectionnum,
                'itemindex' => $itemindex,
                'cmid' => (int) $cm->id,
                'fileid' => (int) $file->get_id(),
                'contenthash' => $file->get_contenthash(),
                'displayname' => $basename . ($ext !== '' ? '.' . $ext : ''),
                'mimetype' => $mime,
                'format' => '',
            ];
        }
        return $itemindex;
    }
}
