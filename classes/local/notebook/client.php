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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Gemini Notebook Enterprise REST client (Discovery Engine v1alpha).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /**
     * Create a notebook.
     *
     * @param string $title
     * @return array
     */
    public function create(string $title): array {
        $data = $this->request('POST', $this->notebooks_url(), ['title' => $title]);
        if (empty($data['notebookId'])) {
            throw new \moodle_exception('error:notebookapi', 'local_learningprism');
        }
        return $data;
    }

    /**
     * Retrieve a notebook.
     *
     * @param string $notebookid
     * @return array
     */
    public function get(string $notebookid): array {
        return $this->request('GET', $this->notebooks_url() . '/' . rawurlencode($notebookid));
    }

    /**
     * Share a notebook with emails and roles.
     *
     * @param string $notebookid
     * @param array $accountandroles [['email' => string, 'role' => string], ...]
     * @return array
     */
    public function share(string $notebookid, array $accountandroles): array {
        return $this->request(
            'POST',
            $this->notebooks_url() . '/' . rawurlencode($notebookid) . ':share',
            ['accountAndRoles' => $accountandroles]
        );
    }

    /**
     * Delete sources by resource name.
     *
     * @param string $notebookid
     * @param string[] $names
     * @return array
     */
    public function batch_delete_sources(string $notebookid, array $names): array {
        if (!$names) {
            return [];
        }
        return $this->request(
            'POST',
            $this->notebooks_url() . '/' . rawurlencode($notebookid) . '/sources:batchDelete',
            ['names' => array_values($names)]
        );
    }

    /**
     * Upload a local file as a notebook source without loading it into a PHP string.
     *
     * @param string $notebookid
     * @param string $filepath
     * @param string $displayname
     * @param string $contenttype
     * @return array
     */
    public function upload_file(string $notebookid, string $filepath, string $displayname, string $contenttype): array {
        if (!is_readable($filepath)) {
            throw new \moodle_exception('error:notebookupload', 'local_learningprism');
        }
        $size = filesize($filepath);
        $fh = fopen($filepath, 'rb');
        if ($fh === false) {
            throw new \moodle_exception('error:notebookupload', 'local_learningprism');
        }

        $url = $this->upload_url() . '/' . rawurlencode($notebookid) . '/sources:uploadFile';
        $headers = [
            'Authorization: Bearer ' . access_token::for_notebook(),
            'X-Goog-Upload-File-Name: ' . $this->header_filename($displayname),
            'X-Goog-Upload-Protocol: raw',
            'Content-Type: ' . $contenttype,
            'Content-Length: ' . (string) $size,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_UPLOAD => true,
            CURLOPT_INFILE => $fh,
            CURLOPT_INFILESIZE => $size,
            CURLOPT_TIMEOUT => 180,
        ]);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fh);

        if ($errno || $raw === false) {
            throw new \moodle_exception('error:notebookapi', 'local_learningprism');
        }
        $data = json_decode((string) $raw, true);
        if ($http >= 400 || empty($data['sourceId']['id'])) {
            $message = $this->error_message($data, $raw);
            throw new \moodle_exception('error:notebookapi', 'local_learningprism', '', null, $message);
        }
        return $data;
    }

    /**
     * Resource name for a source (used by batchDelete).
     *
     * @param string $notebookid
     * @param string $sourceid
     * @return string
     */
    public function source_name(string $notebookid, string $sourceid): string {
        $project = settings::project_number();
        $location = settings::location();
        return 'projects/' . $project . '/locations/' . $location . '/notebooks/' . $notebookid .
            '/source/' . $sourceid;
    }

    /**
     * @param string $method
     * @param string $url
     * @param array|null $payload
     * @return array
     */
    private function request(string $method, string $url, ?array $payload = null): array {
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . access_token::for_notebook(),
            'Content-Type: application/json',
        ]);
        if ($method === 'GET') {
            $raw = $curl->get($url);
        } else {
            $raw = $curl->post($url, json_encode($payload ?? new \stdClass()));
        }
        $info = $curl->get_info();
        $http = (int) ($info['http_code'] ?? 0);
        $data = json_decode((string) $raw, true);
        if ($data === null) {
            $data = [];
        }
        if ($http >= 400) {
            $message = $this->error_message($data, (string) $raw);
            throw new \moodle_exception('error:notebookapi', 'local_learningprism', '', null, $message);
        }
        return $data;
    }

    /**
     * @return string
     */
    private function notebooks_url(): string {
        return $this->host() . '/v1alpha/projects/' . settings::project_number() .
            '/locations/' . settings::location() . '/notebooks';
    }

    /**
     * @return string
     */
    private function upload_url(): string {
        return $this->host() . '/upload/v1alpha/projects/' . settings::project_number() .
            '/locations/' . settings::location() . '/notebooks';
    }

    /**
     * @return string
     */
    private function host(): string {
        return 'https://' . settings::location() . '-discoveryengine.googleapis.com';
    }

    /**
     * @param string $name
     * @return string
     */
    private function header_filename(string $name): string {
        $name = str_replace(["\r", "\n"], '', $name);
        return clean_filename($name);
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
