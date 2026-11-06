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
    /** Approximate safe chunk size for TTS requests. */
    private const CHUNK_SIZE = 4000;

    public function is_configured(): bool {
        $json = access_token::tts_json();
        return $json !== '' && !empty(json_decode($json, true));
    }

    public function synthesize(string $text, array $options = []): string {
        $chunks = self::chunk_text($text, self::CHUNK_SIZE);
        $audio = '';
        foreach ($chunks as $chunk) {
            $audio .= $this->synthesize_chunk($chunk, $options);
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
        $voice = $options['voice'] ?? get_config('local_learningprism', 'gcpvoice') ?: 'en-US-Neural2-A';
        $language = $options['language'] ?? get_config('local_learningprism', 'gcplanguage') ?: 'en-US';

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

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        $response = $curl->post(
            'https://texttospeech.googleapis.com/v1/text:synthesize',
            json_encode($payload)
        );
        $data = json_decode($response, true);
        if (empty($data['audioContent'])) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }
        $decoded = base64_decode($data['audioContent'], true);
        if ($decoded === false) {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }
        return $decoded;
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
