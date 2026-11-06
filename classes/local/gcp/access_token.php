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

namespace local_learningprism\local\gcp;

defined('MOODLE_INTERNAL') || die();

/**
 * Google Cloud service-account JWT access tokens.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_token {
    /** OAuth2 token endpoint. */
    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Default JWT scope (TTS / NotebookLM). */
    public const SCOPE = 'https://www.googleapis.com/auth/cloud-platform';

    /** Drive API scope (course archive with domain-wide delegation). */
    public const SCOPE_DRIVE = 'https://www.googleapis.com/auth/drive';

    /** Admin SDK Groups scope (create/lookup Google Groups for archive sharing). */
    public const SCOPE_DIRECTORY_GROUP = 'https://www.googleapis.com/auth/admin.directory.group';

    /** Admin SDK Group membership scope (add/remove enrolled users in a group). */
    public const SCOPE_DIRECTORY_GROUP_MEMBER = 'https://www.googleapis.com/auth/admin.directory.group.member';

    /** Groups Settings API (lock down who can post / view members / contact owners). */
    public const SCOPE_GROUPS_SETTINGS = 'https://www.googleapis.com/auth/apps.groups.settings';

    /**
     * Service-account JSON for TTS (always the TTS field).
     *
     * @return string
     */
    public static function tts_json(): string {
        return trim((string) get_config('local_learningprism', 'gcpcredentials'));
    }

    /**
     * Service-account JSON for NotebookLM (optional dedicated JSON, else TTS JSON).
     *
     * @return string
     */
    public static function notebook_json(): string {
        $json = trim((string) get_config('local_learningprism', 'nbcredentials'));
        if ($json !== '') {
            return $json;
        }
        return self::tts_json();
    }

    /**
     * Service-account JSON for Drive archive (optional dedicated JSON, else TTS JSON).
     *
     * @return string
     */
    public static function archive_json(): string {
        $json = trim((string) get_config('local_learningprism', 'archivecredentials'));
        if ($json !== '') {
            return $json;
        }
        return self::tts_json();
    }

    /**
     * Access token for Google Cloud TTS.
     *
     * @return string
     */
    public static function for_tts(): string {
        return self::from_json(self::tts_json(), 'error:ttsnotconfigured');
    }

    /**
     * Access token for NotebookLM Enterprise.
     *
     * @return string
     */
    public static function for_notebook(): string {
        return self::from_json(self::notebook_json(), 'error:notebooknotconfigured');
    }

    /**
     * Access token for Google Drive (+ Directory Groups for optional group create).
     *
     * @param string|null $subject Workspace user to impersonate; null uses site setting
     * @return string
     */
    public static function for_drive(?string $subject = null): string {
        if ($subject === null) {
            $subject = trim((string) get_config('local_learningprism', 'archivedelegateduser'));
        }
        $scope = self::SCOPE_DRIVE . ' ' . self::SCOPE_DIRECTORY_GROUP . ' ' .
            self::SCOPE_DIRECTORY_GROUP_MEMBER . ' ' . self::SCOPE_GROUPS_SETTINGS;
        return self::from_json(
            self::archive_json(),
            'error:archivenotconfigured',
            $scope,
            $subject !== '' ? $subject : null
        );
    }

    /**
     * Exchange a service-account JSON key for a short-lived access token.
     *
     * @param string $json
     * @param string $errorcode Lang string if credentials are unusable
     * @param string $scope OAuth scope
     * @param string|null $subject Optional Workspace user for domain-wide delegation
     * @return string
     */
    public static function from_json(
        string $json,
        string $errorcode = 'error:ttsnotconfigured',
        string $scope = self::SCOPE,
        ?string $subject = null
    ): string {
        $creds = json_decode($json, true);
        if (empty($creds['client_email']) || empty($creds['private_key'])) {
            throw new \moodle_exception($errorcode, 'local_learningprism');
        }

        $now = time();
        $claim = [
            'iss' => $creds['client_email'],
            'scope' => $scope,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];
        if ($subject !== null && $subject !== '') {
            $claim['sub'] = $subject;
        }
        $header = self::b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claimenc = self::b64url(json_encode($claim));
        $unsigned = $header . '.' . $claimenc;
        $signature = '';
        $key = str_replace(["\r\n", "\n", "\r"], "\n", $creds['private_key']);
        $ok = openssl_sign($unsigned, $signature, $key, 'SHA256');
        if (!$ok) {
            throw new \moodle_exception($errorcode, 'local_learningprism');
        }
        $jwt = $unsigned . '.' . self::b64url($signature);

        $curl = new \curl();
        $response = $curl->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);
        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new \moodle_exception($errorcode, 'local_learningprism');
        }
        return $data['access_token'];
    }

    /**
     * @param string $data
     * @return string
     */
    public static function b64url(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
