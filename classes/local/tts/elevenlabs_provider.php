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

defined('MOODLE_INTERNAL') || die();

/**
 * ElevenLabs text-to-speech provider.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class elevenlabs_provider implements tts_provider_interface {
    private const CHUNK_SIZE = 4000;
    private const API_BASE = 'https://api.elevenlabs.io/v1';

    public function is_configured(): bool {
        $key = trim((string) get_config('local_learningprism', 'elevenlabskey'));
        $voice = trim((string) get_config('local_learningprism', 'elevenlabsvoice'));
        // ElevenLabs secret keys start with sk_. Key IDs alone are rejected by the API.
        return $key !== '' && str_starts_with($key, 'sk_') && $voice !== '';
    }

    public function synthesize(string $text, array $options = []): string {
        if (!$this->is_configured()) {
            throw new \moodle_exception(
                'error:ttsnotconfigured',
                'local_learningprism',
                '',
                null,
                'ElevenLabs needs a secret API key starting with sk_ and a Voice ID'
            );
        }
        $chunks = google_provider::chunk_text($text, self::CHUNK_SIZE);
        $audio = '';
        foreach ($chunks as $chunk) {
            if (trim($chunk) === '') {
                continue;
            }
            $audio .= $this->synthesize_chunk($chunk, $options);
        }
        if ($audio === '') {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism', '', null, 'No speech text');
        }
        return $audio;
    }

    /**
     * @param string $text
     * @param array $options
     * @return string
     */
    private function synthesize_chunk(string $text, array $options): string {
        $key = trim((string) get_config('local_learningprism', 'elevenlabskey'));
        $voice = trim((string) ($options['voice'] ?? get_config('local_learningprism', 'elevenlabsvoice')));
        $url = self::API_BASE . '/text-to-speech/' . rawurlencode($voice);

        $payload = json_encode([
            'text' => $text,
            'model_id' => $options['model_id'] ?? 'eleven_multilingual_v2',
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism', '', null, 'JSON encode failed');
        }

        $curl = new \curl();
        // Reset headers each request; pass JSON as raw POST body (string, not array).
        $curl->setHeader([
            'xi-api-key: ' . $key,
            'Content-Type: application/json',
            'Accept: audio/mpeg',
            'Content-Length: ' . (string) strlen($payload),
        ]);
        $response = $curl->post($url, $payload, [
            'CURLOPT_TIMEOUT' => 120,
        ]);

        if ($curl->get_errno()) {
            throw new \moodle_exception(
                'error:conversionfailed',
                'local_learningprism',
                '',
                null,
                'ElevenLabs curl: ' . $curl->error
            );
        }
        if ($response === '' || $response === false || $response === null) {
            $info = $curl->get_info();
            $http = is_array($info) && isset($info['http_code']) ? $info['http_code'] : '?';
            throw new \moodle_exception(
                'error:conversionfailed',
                'local_learningprism',
                '',
                null,
                'ElevenLabs empty response (HTTP ' . $http . ') for voice ' . $voice
            );
        }
        // Error payloads are JSON.
        if (isset($response[0]) && $response[0] === '{') {
            $decoded = json_decode($response, true);
            $detail = is_array($decoded)
                ? ($decoded['detail']['message'] ?? $decoded['detail'] ?? $decoded['message'] ?? $response)
                : $response;
            if (is_array($detail)) {
                $detail = json_encode($detail);
            }
            throw new \moodle_exception(
                'error:conversionfailed',
                'local_learningprism',
                '',
                null,
                'ElevenLabs: ' . $detail
            );
        }
        return $response;
    }
}
