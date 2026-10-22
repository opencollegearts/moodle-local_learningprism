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

defined('MOODLE_INTERNAL') || die();

/**
 * Site and course enablement helpers.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** Format constants. */
    public const FORMAT_DOCX = 'docx';
    public const FORMAT_PDF = 'pdf';
    public const FORMAT_EPUB = 'epub';
    public const FORMAT_AUDIO = 'audio';

    /** Job status constants. */
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_ERROR = 'error';

    /** Request types. */
    public const REQUEST_TYPE_REQUEST = 'request';
    public const REQUEST_TYPE_DOWNLOAD = 'download';

    /** File API. */
    public const COMPONENT = 'local_learningprism';
    public const FILEAREA = 'formats';

    /**
     * Whether the plugin is enabled site-wide.
     *
     * @return bool
     */
    public static function plugin_enabled(): bool {
        return (bool) get_config('local_learningprism', 'enable');
    }

    /**
     * Whether Learning Prism is enabled for a course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_enabled(int $courseid): bool {
        if ($courseid <= 0 || !self::plugin_enabled()) {
            return false;
        }
        try {
            $cache = \cache::make('local_learningprism', 'courseenabled');
            $cached = $cache->get($courseid);
            if ($cached !== false) {
                return (bool) $cached;
            }
            global $DB;
            $enabled = (int) $DB->get_field('local_learningprism_course', 'enabled', ['courseid' => $courseid]);
            $cache->set($courseid, $enabled);
            return (bool) $enabled;
        } catch (\Throwable $e) {
            // Do not call debugging() — Open LMS/Whoops promotes notices to fatals.
            error_log('local_learningprism course_enabled: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Set course enablement.
     *
     * @param int $courseid
     * @param bool $enabled
     */
    public static function set_course_enabled(int $courseid, bool $enabled): void {
        global $DB;
        $record = $DB->get_record('local_learningprism_course', ['courseid' => $courseid]);
        $now = time();
        if ($record) {
            $record->enabled = $enabled ? 1 : 0;
            $record->timemodified = $now;
            $DB->update_record('local_learningprism_course', $record);
        } else {
            $record = (object) [
                'courseid' => $courseid,
                'enabled' => $enabled ? 1 : 0,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_learningprism_course', $record);
        }
        $cache = \cache::make('local_learningprism', 'courseenabled');
        $cache->set($courseid, $enabled ? 1 : 0);
    }

    /**
     * List formats enabled in admin settings (and configured).
     *
     * @return string[]
     */
    public static function enabled_formats(): array {
        $formats = [];
        if (get_config('local_learningprism', 'enabledocx')) {
            $formats[] = self::FORMAT_DOCX;
        }
        if (get_config('local_learningprism', 'enablepdf')) {
            $engine = get_config('local_learningprism', 'pdfengine');
            if ($engine && $engine !== 'none') {
                $formats[] = self::FORMAT_PDF;
            }
        }
        if (get_config('local_learningprism', 'enableepub')) {
            $formats[] = self::FORMAT_EPUB;
        }
        if (get_config('local_learningprism', 'enableaudio')) {
            $formats[] = self::FORMAT_AUDIO;
        }
        return $formats;
    }

    /**
     * Formats that support print colour/font personalisation.
     *
     * @param string $format
     * @return bool
     */
    public static function is_print_format(string $format): bool {
        return in_array($format, [self::FORMAT_DOCX, self::FORMAT_PDF, self::FORMAT_EPUB], true);
    }

    /**
     * Supported activity module names.
     *
     * @return string[]
     */
    public static function supported_modules(): array {
        return ['book', 'page', 'label', 'scorm'];
    }
}
