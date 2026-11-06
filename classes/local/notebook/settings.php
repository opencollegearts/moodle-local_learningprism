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

use local_learningprism\local\gcp\access_token;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * NotebookLM Enterprise site configuration.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class settings {
    /** Google documented source size cap. */
    public const MAX_BYTES_CAP = 524288000;

    /** Google documented sources-per-notebook cap. */
    public const MAX_SOURCES = 300;

    /**
     * Feature is on and credentials/project are usable.
     *
     * @return bool
     */
    public static function enabled(): bool {
        if (!manager::plugin_enabled()) {
            return false;
        }
        if (!get_config('local_learningprism', 'enablenotebook')) {
            return false;
        }
        return self::is_configured();
    }

    /**
     * Project number and service-account JSON are present.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $project = self::project_number();
        if ($project === '') {
            return false;
        }
        $json = access_token::notebook_json();
        if ($json === '') {
            return false;
        }
        $creds = json_decode($json, true);
        return !empty($creds['client_email']) && !empty($creds['private_key']);
    }

    /**
     * Numeric GCP project number.
     *
     * @return string
     */
    public static function project_number(): string {
        $value = trim((string) get_config('local_learningprism', 'nbprojectnumber'));
        if ($value === '' || !preg_match('/^[1-9][0-9]*$/', $value)) {
            return '';
        }
        return $value;
    }

    /**
     * API and notebook location (global, us, or eu).
     *
     * @return string
     */
    public static function location(): string {
        $value = (string) get_config('local_learningprism', 'nblocation');
        if (!in_array($value, ['global', 'us', 'eu'], true)) {
            return 'global';
        }
        return $value;
    }

    /**
     * Converted activity format to upload.
     *
     * @return string manager::FORMAT_DOCX or FORMAT_PDF
     */
    public static function source_format(): string {
        $value = (string) get_config('local_learningprism', 'nbsourceformat');
        if ($value === manager::FORMAT_PDF) {
            $engine = get_config('local_learningprism', 'pdfengine');
            if ($engine && $engine !== 'none') {
                return manager::FORMAT_PDF;
            }
        }
        return manager::FORMAT_DOCX;
    }

    /**
     * Browser host for the student/teacher notebook URL.
     *
     * @return string
     */
    public static function identity_host(): string {
        if (get_config('local_learningprism', 'nbidentity') === 'thirdparty') {
            return 'notebook.cloud.google';
        }
        return 'notebook.cloud.google.com';
    }

    /**
     * Max upload bytes, capped at Google's 500 MB limit.
     *
     * @return int
     */
    public static function max_bytes(): int {
        $value = (int) get_config('local_learningprism', 'nbmaxbytes');
        if ($value <= 0) {
            $value = self::MAX_BYTES_CAP;
        }
        return min($value, self::MAX_BYTES_CAP);
    }

    /**
     * How many Notebook sources to upload in one scheduled-task run.
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
     * Public notebook URL for a notebook id.
     *
     * @param string $notebookid
     * @return string
     */
    public static function notebook_url(string $notebookid): string {
        $host = self::identity_host();
        $location = self::location();
        $project = self::project_number();
        return 'https://' . $host . '/' . $location . '/notebook/' . rawurlencode($notebookid) .
            '?project=' . rawurlencode($project);
    }
}
