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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Google Drive v3 client for course archives (domain-wide delegation).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class drive_client {
    public const DRIVE_API = 'https://www.googleapis.com/drive/v3';
    public const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';

    /**
     * Create a folder under an optional parent (My Drive root if empty).
     *
     * @param string $name
     * @param string $parentid
     * @return array{id: string, webViewLink?: string}
     */
    public function create_folder(string $name, string $parentid = ''): array {
        $body = [
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ];
        if ($parentid !== '') {
            $body['parents'] = [$parentid];
        }
        $data = $this->request('POST', '/files?fields=id,webViewLink&supportsAllDrives=true', $body);
        if (empty($data['id'])) {
            throw new \moodle_exception('error:archiveapi', 'local_learningprism', '', null, 'Folder create failed');
        }
        return $data;
    }

    /**
     * Find or create a folder by name under a parent.
     *
     * @param string $name
     * @param string $parentid
     * @return string Folder id
     */
    public function ensure_folder(string $name, string $parentid): string {
        $escaped = str_replace("'", "\\'", $name);
        $parentescaped = str_replace("'", "\\'", $parentid);
        $q = "name = '{$escaped}' and mimeType = 'application/vnd.google-apps.folder' and trashed = false"
            . " and '{$parentescaped}' in parents";
        $list = $this->request(
            'GET',
            '/files?q=' . rawurlencode($q) . '&fields=files(id)&pageSize=1&supportsAllDrives=true&includeItemsFromAllDrives=true'
        );
        if (!empty($list['files'][0]['id'])) {
            return (string) $list['files'][0]['id'];
        }
        $created = $this->create_folder($name, $parentid);
        return (string) $created['id'];
    }

    /**
     * Permanently delete a Drive file or folder (and contents for folders via trash then delete).
     *
     * @param string $fileid
     */
    public function delete(string $fileid): void {
        $id = $this->encode_id($fileid);
        // Trash first so nested content is removed from the active Drive view, then delete.
        try {
            $this->request('PATCH', '/files/' . $id . '?supportsAllDrives=true', ['trashed' => true]);
        } catch (\Throwable $e) {
            // Continue to delete attempt.
        }
        try {
            $this->request('DELETE', '/files/' . $id . '?supportsAllDrives=true');
        } catch (\Throwable $e) {
            debugging('local_learningprism archive delete: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Multipart upload of a local file into a Drive folder.
     *
     * @param string $filepath
     * @param string $displayname
     * @param string $mimetype
     * @param string $parentid
     * @return array{id: string, webViewLink?: string}
     */
    public function upload_file(string $filepath, string $displayname, string $mimetype, string $parentid): array {
        if (!is_readable($filepath)) {
            throw new \moodle_exception('error:archiveupload', 'local_learningprism');
        }
        $size = filesize($filepath);
        $metadata = json_encode([
            'name' => $displayname,
            'parents' => [$parentid],
        ]);
        $boundary = '=======learningprism_' . uniqid('', true);
        $body = '--' . $boundary . "\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
            . $metadata . "\r\n"
            . '--' . $boundary . "\r\n"
            . 'Content-Type: ' . $mimetype . "\r\n\r\n";
        $filedata = file_get_contents($filepath);
        if ($filedata === false) {
            throw new \moodle_exception('error:archiveupload', 'local_learningprism');
        }
        $body .= $filedata . "\r\n--" . $boundary . "--";

        $url = self::UPLOAD_API . '/files?uploadType=multipart&fields=id,webViewLink&supportsAllDrives=true';
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . access_token::for_drive(),
            'Content-Type: multipart/related; boundary=' . $boundary,
            'Content-Length: ' . (string) strlen($body),
        ]);
        $raw = $curl->post($url, $body);
        $info = $curl->get_info();
        $http = (int) ($info['http_code'] ?? 0);
        $data = json_decode((string) $raw, true);
        if ($http >= 400 || empty($data['id'])) {
            $message = $this->error_message(is_array($data) ? $data : [], (string) $raw);
            throw new \moodle_exception('error:archiveapi', 'local_learningprism', '', null, $message);
        }
        unset($size); // Used for readability of upload intent.
        return $data;
    }

    /**
     * @param string $fileid
     * @return string
     */
    public function web_view_link(string $fileid): string {
        $data = $this->request(
            'GET',
            '/files/' . $this->encode_id($fileid) . '?fields=webViewLink&supportsAllDrives=true'
        );
        if (!empty($data['webViewLink'])) {
            return (string) $data['webViewLink'];
        }
        return 'https://drive.google.com/drive/folders/' . rawurlencode($fileid);
    }

    /**
     * List permissions on a file/folder.
     *
     * @param string $fileid
     * @return array
     */
    public function list_permissions(string $fileid): array {
        $response = $this->request(
            'GET',
            '/files/' . $this->encode_id($fileid) .
                '/permissions?supportsAllDrives=true&fields=permissions(id,type,emailAddress,role)'
        );
        return $response['permissions'] ?? [];
    }

    /**
     * Share with a user or Google Group.
     *
     * @param string $fileid
     * @param string $email
     * @param string $role reader|writer|commenter
     * @param string $type user|group
     * @return string|null Permission id
     */
    public function add_permission(
        string $fileid,
        string $email,
        string $role = 'reader',
        string $type = 'user'
    ): ?string {
        $query = http_build_query([
            'fields' => 'id',
            'supportsAllDrives' => 'true',
            'sendNotificationEmail' => 'false',
        ]);
        $result = $this->request(
            'POST',
            '/files/' . $this->encode_id($fileid) . '/permissions?' . $query,
            [
                'type' => $type,
                'role' => $role,
                'emailAddress' => $email,
            ]
        );
        $id = trim((string) ($result['id'] ?? ''));
        return $id !== '' ? $id : null;
    }

    /**
     * Anyone with the link can view (no email).
     *
     * @param string $fileid
     * @param string $role
     * @return string|null
     */
    public function add_anyone_permission(string $fileid, string $role = 'reader'): ?string {
        $query = http_build_query([
            'fields' => 'id',
            'supportsAllDrives' => 'true',
        ]);
        $result = $this->request(
            'POST',
            '/files/' . $this->encode_id($fileid) . '/permissions?' . $query,
            [
                'type' => 'anyone',
                'role' => $role,
                'allowFileDiscovery' => false,
            ]
        );
        $id = trim((string) ($result['id'] ?? ''));
        return $id !== '' ? $id : null;
    }

    /**
     * @param string $fileid
     * @param string $permissionid
     */
    public function delete_permission(string $fileid, string $permissionid): void {
        $this->request(
            'DELETE',
            '/files/' . $this->encode_id($fileid) . '/permissions/' .
                rawurlencode(trim($permissionid)) . '?supportsAllDrives=true'
        );
    }

    /**
     * Replace managed share permissions (user/group/anyone) with the given targets.
     *
     * @param string $fileid
     * @param array $targets Each: type (user|group|anyone), role, optional email
     */
    public function replace_share_permissions(string $fileid, array $targets): void {
        $perms = $this->list_permissions($fileid);
        foreach ($perms as $perm) {
            $type = (string) ($perm['type'] ?? '');
            $role = (string) ($perm['role'] ?? '');
            if ($role === 'owner' || empty($perm['id'])) {
                continue;
            }
            if (!in_array($type, ['user', 'group', 'anyone'], true)) {
                continue;
            }
            try {
                $this->delete_permission($fileid, (string) $perm['id']);
            } catch (\Throwable $e) {
                debugging('local_learningprism archive delete permission: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
        foreach ($targets as $target) {
            $type = (string) ($target['type'] ?? 'user');
            $role = (string) ($target['role'] ?? 'reader');
            if ($type === 'anyone') {
                $this->add_anyone_permission($fileid, $role);
                continue;
            }
            $email = trim((string) ($target['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $this->add_permission($fileid, $email, $role, $type);
        }
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array|null $payload
     * @return array
     */
    private function request(string $method, string $endpoint, ?array $payload = null): array {
        $url = self::DRIVE_API . (strpos($endpoint, '/') === 0 ? $endpoint : '/' . $endpoint);
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . access_token::for_drive(),
            'Content-Type: application/json',
        ]);
        if ($method === 'GET') {
            $raw = $curl->get($url);
        } else if ($method === 'DELETE') {
            $raw = $curl->delete($url);
        } else if ($method === 'PATCH') {
            $raw = $curl->patch($url, json_encode($payload ?? new \stdClass()));
        } else {
            $raw = $curl->post($url, json_encode($payload ?? new \stdClass()));
        }
        $info = $curl->get_info();
        $http = (int) ($info['http_code'] ?? 0);
        // DELETE may return empty 204.
        if ($method === 'DELETE' && ($http === 204 || $http === 200)) {
            return [];
        }
        $data = json_decode((string) $raw, true);
        if ($data === null) {
            $data = [];
        }
        if ($http >= 400) {
            $message = $this->error_message($data, (string) $raw);
            throw new \moodle_exception('error:archiveapi', 'local_learningprism', '', null, $message);
        }
        return $data;
    }

    /**
     * @param string $id
     * @return string
     */
    private function encode_id(string $id): string {
        $id = trim($id);
        if ($id === '' || !preg_match('/^[A-Za-z0-9_-]{10,256}$/', $id)) {
            throw new \moodle_exception('error:archiveapi', 'local_learningprism', '', null, 'Invalid Drive id');
        }
        return rawurlencode($id);
    }

    /**
     * @param array $data
     * @param string $raw
     * @return string
     */
    private function error_message(array $data, string $raw): string {
        if (!empty($data['error']['message'])) {
            return (string) $data['error']['message'];
        }
        $trim = trim($raw);
        return $trim !== '' ? $trim : 'HTTP error';
    }
}
