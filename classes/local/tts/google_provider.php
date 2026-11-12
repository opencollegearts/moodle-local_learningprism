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

namespace local_learningprism\local\tts;

use local_learningprism\local\gcp\access_token;

defined('MOODLE_INTERNAL') || die();

/**
 * Google Cloud Text-to-Speech provider (API key / OAuth access token via service account JWT).
 *
 * Uses the REST synthesize endpoint. Service account JSON from admin settings is used to
 * obtain a short-lived access token via OAuth2 JWT bearer flow.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class google_provider implements tts_provider_interface {
    /** Approximate safe chunk size for TTS requests (Chirp 3 is tighter than Neural2). */
    private const CHUNK_SIZE = 3000;

    public function is_configured(): bool {
        $json = access_token::tts_json();
        return $json !== '' && !empty(json_decode($json, true));
    }

    public function synthesize(string $text, array $options = []): string {
        $chunks = self::chunk_text($text, self::CHUNK_SIZE);
        $audio = '';
        foreach ($chunks as $chunk) {
            if (trim($chunk) === '') {
                continue;
            }
            $audio .= $this->synthesize_chunk($chunk, $options);
        }
        if ($audio === '') {
            throw new \moodle_exception(
                'error:conversionfailed',
                'local_learningprism',
                '',
                'Google TTS: no speech text after chunking'
            );
        }
        return $audio;
    }

    /**
     * @param string $text
     * @param array $options
     * @return string mp3 bytes
     */
    private function synthesize_chunk(string $text, array $options): string {
        $token = access_token::for_tts();
        $voice = trim((string) ($options['voice'] ?? voice_catalog::site_default(voice_catalog::PROVIDER_GOOGLE)));
        if ($voice === '') {
            $voice = 'en-US-Neural2-A';
        }
        // Voice names encode the locale (en-GB-Chirp3-HD-Fenrir). Prefer that over a
        // mismatched site language setting — Google rejects Chirp/Neural voices when
        // languageCode does not match the voice prefix.
        $language = self::language_for_voice($voice, $options['language'] ?? null);
        $text = self::utf8_text($text);

        $payload = [
            'input' => ['text' => $text],
            'voice' => [
                'languageCode' => $language,
                'name' => $voice,
            ],
            'audioConfig' => [
                'audioEncoding' => 'MP3',
            ],
        ];
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($body === false || $body === '' || $body === 'null') {
            throw new \moodle_exception(
                'error:conversionfailed',
                'local_learningprism',
                '',
                'Google TTS: failed to encode request JSON for voice ' . $voice
            );
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . (string) strlen($body),
            'Accept: application/json',
        ];
        $project = access_token::tts_project_id();
        if ($project !== '') {
            // Required for some service-account calls when the token has no project quota.
            $headers[] = 'x-goog-user-project: ' . $project;
        }

        $curl = new \curl();
        $curl->setHeader($headers);
        $response = $curl->post(
            'https://texttospeech.googleapis.com/v1/text:synthesize',
            $body,
            ['CURLOPT_TIMEOUT' => 120]
        );
        if ($curl->get_errno()) {
            throw new \moodle_exception(
                'error:conversionfailed',
                'local_learningprism',
                '',
                'Google TTS curl: ' . $curl->error
            );
        }
        $info = $curl->get_info();
        $http = (int) ($info['http_code'] ?? 0);
        $data = json_decode((string) $response, true);
        if (empty($data['audioContent'])) {
            throw new \moodle_exception(
                'error:conversionfailed',
                'local_learningprism',
                '',
                self::api_error_detail($data, (string) $response, $voice, $language, $http)
            );
        }
        $decoded = base64_decode($data['audioContent'], true);
        if ($decoded === false || $decoded === '') {
            throw new \moodle_exception(
                'error:conversionfailed',
                'local_learningprism',
                '',
                'Google TTS returned invalid audio content'
            );
        }
        return $decoded;
    }

    /**
     * Prefer locale embedded in the voice name (en-GB-…).
     *
     * @param string $voice
     * @param string|null $fallback
     * @return string
     */
    public static function language_for_voice(string $voice, ?string $fallback = null): string {
        if (preg_match('/^([a-z]{2,3}-[A-Z]{2})\b/', $voice, $matches)) {
            return $matches[1];
        }
        $configured = trim((string) ($fallback ?? get_config('local_learningprism', 'gcplanguage') ?: 'en-US'));
        return $configured !== '' ? $configured : 'en-US';
    }

    /**
     * Ensure plain UTF-8 text for JSON + Google TTS.
     *
     * @param string $text
     * @return string
     */
    private static function utf8_text(string $text): string {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }
        // Strip NULs and other controls that break some Google models.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text) ?? $text;
        return trim($text);
    }

    /**
     * Human-readable Google API error for the course report / modal.
     *
     * @param array|null $data
     * @param string $raw
     * @param string $voice
     * @param string $language
     * @param int $http
     * @return string
     */
    private static function api_error_detail(
        ?array $data,
        string $raw,
        string $voice,
        string $language,
        int $http = 0
    ): string {
        $message = '';
        if (is_array($data) && !empty($data['error']['message'])) {
            $message = (string) $data['error']['message'];
        } else if (is_array($data) && !empty($data['error']['status'])) {
            $message = (string) $data['error']['status'];
        } else {
            $snippet = trim(preg_replace('/\s+/', ' ', substr(strip_tags($raw), 0, 180)) ?? '');
            $message = $snippet !== '' ? $snippet : 'empty response';
        }
        $prefix = 'Google TTS (' . $voice . ' / ' . $language . ')';
        if ($http > 0) {
            $prefix .= ' HTTP ' . $http;
        }
        return $prefix . ': ' . $message;
    }

    /**
     * Split text on sentence boundaries where possible.
     *
     * @param string $text
     * @param int $size
     * @return string[]
     */
    public static function chunk_text(string $text, int $size): array {
        $text = trim($text);
        if ($text === '') {
            return [''];
        }
        if (\core_text::strlen($text) <= $size) {
            return [$text];
        }
        $chunks = [];
        $remaining = $text;
        while (\core_text::strlen($remaining) > $size) {
            $slice = \core_text::substr($remaining, 0, $size);
            $break = max(
                strrpos($slice, '. '),
                strrpos($slice, "! "),
                strrpos($slice, "? "),
                strrpos($slice, "\n")
            );
            if ($break === false || $break < (int) ($size * 0.5)) {
                $break = $size;
            } else {
                $break += 1;
            }
            $chunks[] = trim(\core_text::substr($remaining, 0, $break));
            $remaining = trim(\core_text::substr($remaining, $break));
        }
        if ($remaining !== '') {
            $chunks[] = $remaining;
        }
        return $chunks;
    }
}
