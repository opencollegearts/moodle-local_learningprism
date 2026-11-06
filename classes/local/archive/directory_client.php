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
 * Google Admin SDK Directory client for Google Groups (domain-wide delegation).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class directory_client {
    public const API = 'https://admin.googleapis.com/admin/directory/v1';

    /** Groups Settings API base (JSON). */
    public const SETTINGS_API = 'https://www.googleapis.com/groups/v1/groups';

    /**
     * Return true if the group email already exists.
     *
     * @param string $email
     * @return bool
     */
    public function group_exists(string $email): bool {
        $email = trim($email);
        try {
            $this->request('GET', '/groups/' . rawurlencode($email) . '?fields=id,email');
            return true;
        } catch (\moodle_exception $e) {
            $detail = (string) ($e->debuginfo ?: $e->getMessage());
            if (stripos($detail, 'Not Found') !== false || stripos($detail, '404') !== false) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Create a Google Group if it does not already exist.
     *
     * @param string $email
     * @param string $name
     * @param string $description
     * @return array
     */
    public function ensure_group(string $email, string $name, string $description = ''): array {
        $email = trim($email);
        if ($this->group_exists($email)) {
            return ['email' => $email, 'created' => false];
        }
        $body = [
            'email' => $email,
            'name' => $name !== '' ? $name : $email,
        ];
        if ($description !== '') {
            $body['description'] = $description;
        }
        $created = $this->request('POST', '/groups?fields=id,email,name', $body);
        if (empty($created['email']) && empty($created['id'])) {
            throw new \moodle_exception('error:archivegroupcreate', 'local_learningprism');
        }
        $groupemail = $created['email'] ?? $email;
        $this->apply_member_lockdown($groupemail);
        return ['email' => $groupemail, 'created' => true];
    }

    /**
     * Restrict posting, membership visibility, and contacting owners to group members
     * (not the whole organisation or external users). Also invite-only join.
     *
     * @param string $groupemail
     */
    public function apply_member_lockdown(string $groupemail): void {
        $groupemail = trim($groupemail);
        $settings = [
            // Match UI: only owners/managers/members — not entire organisation or external.
            'whoCanPostMessage' => 'ALL_MEMBERS_CAN_POST',
            'whoCanContactOwner' => 'ALL_MEMBERS_CAN_CONTACT',
            'whoCanViewMembership' => 'ALL_MEMBERS_CAN_VIEW',
            'whoCanViewGroup' => 'ALL_MEMBERS_CAN_VIEW',
            // Membership is managed by Learning Prism; do not allow org-wide ask-to-join.
            'whoCanJoin' => 'INVITED_CAN_JOIN',
            'allowExternalMembers' => 'false',
            'whoCanDiscoverGroup' => 'ALL_IN_DOMAIN_CAN_DISCOVER',
        ];
        $url = self::SETTINGS_API . '/' . rawurlencode($groupemail);
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . access_token::for_drive(),
            'Content-Type: application/json',
        ]);
        $raw = $curl->put($url, json_encode($settings));
        $info = $curl->get_info();
        $http = (int) ($info['http_code'] ?? 0);
        $data = json_decode((string) $raw, true);
        if ($data === null) {
            $data = [];
        }
        if ($http >= 400) {
            $message = !empty($data['error']['message'])
                ? (string) $data['error']['message']
                : (trim((string) $raw) !== '' ? (string) $raw : 'HTTP ' . $http);
            throw new \moodle_exception('error:archivegroupsettings', 'local_learningprism', '', null, $message);
        }
    }

    /**
     * List MEMBER-role email addresses currently in the group (paginated).
     *
     * @param string $groupemail
     * @return string[] Lowercased email => original email
     */
    public function list_member_emails(string $groupemail): array {
        $groupemail = trim($groupemail);
        $members = [];
        $page = null;
        do {
            $endpoint = '/groups/' . rawurlencode($groupemail) .
                '/members?maxResults=200&roles=MEMBER&fields=members(email,type,role),nextPageToken';
            if ($page) {
                $endpoint .= '&pageToken=' . rawurlencode($page);
            }
            $data = $this->request('GET', $endpoint);
            foreach ($data['members'] ?? [] as $member) {
                if (($member['type'] ?? '') !== 'USER') {
                    continue;
                }
                $email = trim((string) ($member['email'] ?? ''));
                if ($email === '' || !validate_email($email)) {
                    continue;
                }
                $members[strtolower($email)] = $email;
            }
            $page = $data['nextPageToken'] ?? null;
        } while ($page);
        return $members;
    }

    /**
     * Add a user as a MEMBER of the group (idempotent if already a member).
     *
     * @param string $groupemail
     * @param string $useremail
     */
    public function add_member(string $groupemail, string $useremail): void {
        try {
            $this->request(
                'POST',
                '/groups/' . rawurlencode(trim($groupemail)) . '/members',
                [
                    'email' => trim($useremail),
                    'role' => 'MEMBER',
                ]
            );
        } catch (\moodle_exception $e) {
            $detail = (string) ($e->debuginfo ?: $e->getMessage());
            // Already in the group.
            if (stripos($detail, 'Member already exists') !== false
                    || stripos($detail, 'duplicate') !== false
                    || stripos($detail, '409') !== false) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Remove a user from the group.
     *
     * @param string $groupemail
     * @param string $useremail
     */
    public function remove_member(string $groupemail, string $useremail): void {
        try {
            $this->request(
                'DELETE',
                '/groups/' . rawurlencode(trim($groupemail)) . '/members/' . rawurlencode(trim($useremail))
            );
        } catch (\moodle_exception $e) {
            $detail = (string) ($e->debuginfo ?: $e->getMessage());
            if (stripos($detail, 'Not Found') !== false || stripos($detail, '404') !== false) {
                return;
            }
            throw $e;
        }
    }

    /**
     * Make group MEMBER list match the desired emails (adds and removes MEMBER users only).
     *
     * @param string $groupemail
     * @param string[] $desiredemails
     * @return array{added: int, removed: int}
     */
    public function sync_members(string $groupemail, array $desiredemails): array {
        $desired = [];
        foreach ($desiredemails as $email) {
            $email = trim((string) $email);
            if ($email === '' || !validate_email($email)) {
                continue;
            }
            $desired[strtolower($email)] = $email;
        }
        $current = $this->list_member_emails($groupemail);
        $added = 0;
        $removed = 0;
        foreach ($desired as $key => $email) {
            if (isset($current[$key])) {
                continue;
            }
            $this->add_member($groupemail, $email);
            $added++;
        }
        foreach ($current as $key => $email) {
            if (isset($desired[$key])) {
                continue;
            }
            $this->remove_member($groupemail, $email);
            $removed++;
        }
        return ['added' => $added, 'removed' => $removed];
    }

    /**
     * @param string $method
     * @param string $endpoint
     * @param array|null $payload
     * @return array
     */
    private function request(string $method, string $endpoint, ?array $payload = null): array {
        $url = self::API . (strpos($endpoint, '/') === 0 ? $endpoint : '/' . $endpoint);
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . access_token::for_drive(),
            'Content-Type: application/json',
        ]);
        if ($method === 'GET') {
            $raw = $curl->get($url);
        } else if ($method === 'DELETE') {
            $raw = $curl->delete($url);
        } else {
            $raw = $curl->post($url, json_encode($payload ?? new \stdClass()));
        }
        $info = $curl->get_info();
        $http = (int) ($info['http_code'] ?? 0);
        if ($method === 'DELETE' && ($http === 204 || $http === 200)) {
            return [];
        }
        $data = json_decode((string) $raw, true);
        if ($data === null) {
            $data = [];
        }
        if ($http >= 400) {
            $message = !empty($data['error']['message'])
                ? (string) $data['error']['message']
                : (trim((string) $raw) !== '' ? (string) $raw : 'HTTP ' . $http);
            throw new \moodle_exception('error:archiveapi', 'local_learningprism', '', null, $message);
        }
        return $data;
    }
}
