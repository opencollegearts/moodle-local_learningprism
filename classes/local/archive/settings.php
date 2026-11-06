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

use local_learningprism\local\gcp\access_token;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Google Drive course archive site configuration.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings {
    /** Default delegated Workspace user for domain-wide delegation. */
    public const DEFAULT_DELEGATED_USER = 'extranet@oca.ac.uk';

    /** Soft cap on archive file size (500 MB). */
    public const MAX_BYTES_CAP = 524288000;

    /**
     * Feature is on and credentials/delegated user are usable.
     *
     * @return bool
     */
    public static function enabled(): bool {
        if (!manager::plugin_enabled()) {
            return false;
        }
        if (!get_config('local_learningprism', 'enablearchive')) {
            return false;
        }
        return self::is_configured();
    }

    /**
     * Service-account JSON and delegated user are present.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        if (self::delegated_user() === '') {
            return false;
        }
        $json = access_token::archive_json();
        if ($json === '') {
            return false;
        }
        $creds = json_decode($json, true);
        return !empty($creds['client_email']) && !empty($creds['private_key']);
    }

    /**
     * Workspace user to impersonate via domain-wide delegation.
     *
     * @return string
     */
    public static function delegated_user(): string {
        $value = trim((string) get_config('local_learningprism', 'archivedelegateduser'));
        if ($value === '') {
            $value = self::DEFAULT_DELEGATED_USER;
        }
        return validate_email($value) ? $value : '';
    }

    /**
     * Optional parent folder ID under the delegated user's Drive.
     *
     * @return string
     */
    public static function parent_folder_id(): string {
        $value = trim((string) get_config('local_learningprism', 'archiveparentfolder'));
        if ($value === '' || !preg_match('/^[A-Za-z0-9_-]{10,256}$/', $value)) {
            return '';
        }
        return $value;
    }

    /**
     * Max native file bytes to upload.
     *
     * @return int
     */
    public static function max_bytes(): int {
        $value = (int) get_config('local_learningprism', 'archivemaxbytes');
        if ($value <= 0) {
            $value = self::MAX_BYTES_CAP;
        }
        return min($value, self::MAX_BYTES_CAP);
    }

    /**
     * How many Drive files to upload in one scheduled-task run.
     *
     * @return int
     */
    public static function uploads_per_run(): int {
        $value = (int) get_config('local_learningprism', 'googleuploadsperrun');
        if ($value <= 0) {
            $value = 1;
        }
        return min(50, $value);
    }

    /**
     * Root folder display name for a course.
     *
     * @param \stdClass $course
     * @return string
     */
    public static function root_folder_name(\stdClass $course): string {
        $short = trim(format_string($course->shortname));
        $full = trim(format_string($course->fullname));
        $name = $short;
        if ($full !== '' && $full !== $short) {
            $name = $short . ' - ' . $full;
        }
        $name = preg_replace('/[\\\\\/:*?"<>|]+/', '-', $name) ?? $name;
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        if (\core_text::strlen($name) > 120) {
            $name = \core_text::substr($name, 0, 120);
        }
        return $name !== '' ? $name : ('course-' . (int) $course->id);
    }
}
