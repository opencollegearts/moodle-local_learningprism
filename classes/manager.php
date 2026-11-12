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
    public const FILEAREA_ARCHIVE = 'archive';

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
            // Developer diagnostics only (DEBUG_DEVELOPER).
            debugging('local_learningprism course_enabled: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
        self::save_course_settings($courseid, ['enabled' => $enabled ? 1 : 0]);
    }

    /**
     * Course settings row (or defaults).
     *
     * @param int $courseid
     * @return \stdClass
     */
    public static function get_course_record(int $courseid): \stdClass {
        global $DB;
        $record = $DB->get_record('local_learningprism_course', ['courseid' => $courseid]);
        if ($record) {
            if (!isset($record->allowstudentaudio)) {
                $record->allowstudentaudio = 0;
            }
            if (!isset($record->defaultvoice)) {
                $record->defaultvoice = '';
            }
            return $record;
        }
        return (object) [
            'id' => 0,
            'courseid' => $courseid,
            'enabled' => 0,
            'allowstudentaudio' => 0,
            'defaultvoice' => '',
            'timemodified' => 0,
        ];
    }

    /**
     * Persist course settings fields.
     *
     * @param int $courseid
     * @param array $fields enabled / allowstudentaudio / defaultvoice
     */
    public static function save_course_settings(int $courseid, array $fields): void {
        global $DB;
        $record = $DB->get_record('local_learningprism_course', ['courseid' => $courseid]);
        $now = time();
        if ($record) {
            if (array_key_exists('enabled', $fields)) {
                $record->enabled = !empty($fields['enabled']) ? 1 : 0;
            }
            if (array_key_exists('allowstudentaudio', $fields)) {
                $record->allowstudentaudio = !empty($fields['allowstudentaudio']) ? 1 : 0;
            }
            if (array_key_exists('defaultvoice', $fields)) {
                $record->defaultvoice = trim((string) $fields['defaultvoice']);
            }
            $record->timemodified = $now;
            $DB->update_record('local_learningprism_course', $record);
        } else {
            $record = (object) [
                'courseid' => $courseid,
                'enabled' => !empty($fields['enabled']) ? 1 : 0,
                'allowstudentaudio' => !empty($fields['allowstudentaudio']) ? 1 : 0,
                'defaultvoice' => trim((string) ($fields['defaultvoice'] ?? '')),
                'timemodified' => $now,
            ];
            $DB->insert_record('local_learningprism_course', $record);
        }
        if (array_key_exists('enabled', $fields)) {
            $cache = \cache::make('local_learningprism', 'courseenabled');
            $cache->set($courseid, !empty($fields['enabled']) ? 1 : 0);
        }
    }

    /**
     * Whether students may request audio in this course.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_allows_student_audio(int $courseid): bool {
        if ($courseid <= 0 || !self::plugin_enabled()) {
            return false;
        }
        $record = self::get_course_record($courseid);
        return !empty($record->allowstudentaudio);
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
     * Formats available to a user in a course (gates student audio).
     *
     * @param int $courseid
     * @param int $userid
     * @return string[]
     */
    public static function enabled_formats_for_user(int $courseid, int $userid): array {
        $formats = self::enabled_formats();
        if (!in_array(self::FORMAT_AUDIO, $formats, true)) {
            return $formats;
        }
        $context = \context_course::instance($courseid);
        if (has_capability('local/learningprism:manage', $context, $userid)) {
            return $formats;
        }
        if (self::course_allows_student_audio($courseid)) {
            return $formats;
        }
        return array_values(array_filter($formats, static function(string $format): bool {
            return $format !== self::FORMAT_AUDIO;
        }));
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
     * Whether a format supports personal copies (print options and/or voice).
     *
     * @param string $format
     * @param int $courseid
     * @return bool
     */
    public static function supports_personal(string $format, int $courseid = 0): bool {
        if (self::is_print_format($format)) {
            return true;
        }
        if ($format === self::FORMAT_AUDIO) {
            return $courseid > 0 && self::course_allows_student_audio($courseid);
        }
        return false;
    }

    /**
     * Enabled formats for Drive archive uploads (admin multiselect, intersection with site).
     *
     * @return string[]
     */
    public static function archive_formats(): array {
        $raw = get_config('local_learningprism', 'archiveformats');
        $chosen = [];
        if (is_array($raw)) {
            $chosen = array_keys(array_filter($raw));
        } else if (is_string($raw) && $raw !== '') {
            // admin_setting_configmulticheckbox stores selected keys as CSV.
            $chosen = array_values(array_filter(array_map('trim', explode(',', $raw))));
        }
        if (!$chosen) {
            $chosen = [self::FORMAT_DOCX];
        }
        $site = self::enabled_formats();
        $out = [];
        foreach ([self::FORMAT_DOCX, self::FORMAT_PDF, self::FORMAT_EPUB, self::FORMAT_AUDIO] as $format) {
            if (in_array($format, $chosen, true) && in_array($format, $site, true)) {
                $out[] = $format;
            }
        }
        if (!$out && in_array(self::FORMAT_DOCX, $site, true)) {
            $out[] = self::FORMAT_DOCX;
        }
        return $out;
    }

    /**
     * Drive subfolder label for a format.
     *
     * @param string $format
     * @return string
     */
    public static function archive_format_folder(string $format): string {
        $map = [
            self::FORMAT_DOCX => 'DocX',
            self::FORMAT_PDF => 'PDF',
            self::FORMAT_EPUB => 'Ebook',
            self::FORMAT_AUDIO => 'Audio',
        ];
        return $map[$format] ?? ucfirst($format);
    }

    /**
     * Supported activity module names.
     *
     * @return string[]
     */
    public static function supported_modules(): array {
        return ['book', 'page', 'label', 'scorm'];
    }

    /**
     * Allow only http(s) URLs for outbound links shown in Moodle UI.
     *
     * @param string $url
     * @return string Empty string when unsafe / invalid
     */
    public static function sanitise_external_url(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $url = clean_param($url, PARAM_URL);
        if ($url === '') {
            return '';
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme'])) {
            return '';
        }
        if (!in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return '';
        }
        return $url;
    }
}
