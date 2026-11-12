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
 * Admin-configured TTS voice catalogs and resolution.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class voice_catalog {
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_ELEVENLABS = 'elevenlabs';

    /**
     * Parse "voice_id|Display name" lines (one per line).
     *
     * @param string $raw
     * @return array<int, array{id: string, label: string}>
     */
    public static function parse_list(string $raw): array {
        $out = [];
        $seen = [];
        foreach (preg_split("/\r\n|\n|\r/", $raw) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $id = $line;
            $label = $line;
            if (str_contains($line, '|')) {
                [$id, $label] = array_map('trim', explode('|', $line, 2));
            }
            if ($id === '') {
                continue;
            }
            if ($label === '') {
                $label = $id;
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = ['id' => $id, 'label' => $label];
        }
        return $out;
    }

    /**
     * Active TTS provider key.
     *
     * @return string
     */
    public static function active_provider(): string {
        $provider = (string) get_config('local_learningprism', 'ttsprovider');
        if ($provider === self::PROVIDER_ELEVENLABS) {
            return self::PROVIDER_ELEVENLABS;
        }
        return self::PROVIDER_GOOGLE;
    }

    /**
     * Raw config key for a provider's voice list.
     *
     * @param string $provider
     * @return string
     */
    public static function list_config_name(string $provider): string {
        return $provider === self::PROVIDER_ELEVENLABS ? 'elevenlabsvoices' : 'gcpvoices';
    }

    /**
     * Raw config key for a provider's site default voice id.
     *
     * @param string $provider
     * @return string
     */
    public static function default_config_name(string $provider): string {
        return $provider === self::PROVIDER_ELEVENLABS ? 'elevenlabsvoicedefault' : 'gcpvoicedefault';
    }

    /**
     * @param string $provider
     * @return array<int, array{id: string, label: string}>
     */
    public static function list_for_provider(string $provider): array {
        $raw = (string) get_config('local_learningprism', self::list_config_name($provider));
        $list = self::parse_list($raw);
        if ($list) {
            return $list;
        }
        // Legacy single-voice settings fallback.
        if ($provider === self::PROVIDER_ELEVENLABS) {
            $legacy = trim((string) get_config('local_learningprism', 'elevenlabsvoice'));
            return $legacy !== '' ? [['id' => $legacy, 'label' => $legacy]] : [];
        }
        $legacy = trim((string) get_config('local_learningprism', 'gcpvoice'));
        if ($legacy === '') {
            $legacy = 'en-US-Neural2-A';
        }
        return [['id' => $legacy, 'label' => $legacy]];
    }

    /**
     * id => label map for selects.
     *
     * @param string $provider
     * @return array<string, string>
     */
    public static function options_for_provider(string $provider): array {
        $options = [];
        foreach (self::list_for_provider($provider) as $row) {
            $options[$row['id']] = $row['label'];
        }
        return $options;
    }

    /**
     * Site default voice id for a provider.
     *
     * @param string $provider
     * @return string
     */
    public static function site_default(string $provider): string {
        $options = self::options_for_provider($provider);
        if (!$options) {
            return '';
        }
        $configured = trim((string) get_config('local_learningprism', self::default_config_name($provider)));
        if ($configured !== '' && isset($options[$configured])) {
            return $configured;
        }
        return (string) array_key_first($options);
    }

    /**
     * Course default voice, falling back to site default.
     *
     * @param int $courseid
     * @param string|null $provider
     * @return string
     */
    public static function course_default(int $courseid, ?string $provider = null): string {
        $provider = $provider ?? self::active_provider();
        $options = self::options_for_provider($provider);
        if ($courseid > 0) {
            global $DB;
            $row = $DB->get_record('local_learningprism_course', ['courseid' => $courseid], 'defaultvoice');
            $coursevoice = $row ? trim((string) $row->defaultvoice) : '';
            if ($coursevoice !== '' && isset($options[$coursevoice])) {
                return $coursevoice;
            }
        }
        return self::site_default($provider);
    }

    /**
     * Resolve voice for a conversion: personal request → course → site.
     *
     * @param int $courseid
     * @param string|null $requestedvoice
     * @param string|null $provider
     * @return string
     */
    public static function resolve(int $courseid, ?string $requestedvoice = null, ?string $provider = null): string {
        $provider = $provider ?? self::active_provider();
        $options = self::options_for_provider($provider);
        $requested = trim((string) $requestedvoice);
        if ($requested !== '' && isset($options[$requested])) {
            return $requested;
        }
        return self::course_default($courseid, $provider);
    }

    /**
     * Whether a voice id is in the provider catalog.
     *
     * @param string $provider
     * @param string $voiceid
     * @return bool
     */
    public static function is_valid(string $provider, string $voiceid): bool {
        $voiceid = trim($voiceid);
        if ($voiceid === '') {
            return false;
        }
        return isset(self::options_for_provider($provider)[$voiceid]);
    }

    /**
     * Default Google catalog line for fresh installs.
     *
     * @return string
     */
    public static function default_google_list(): string {
        return "en-US-Neural2-A|US English Neural2 A\n"
            . "en-US-Neural2-C|US English Neural2 C\n"
            . "en-GB-Neural2-A|UK English Neural2 A\n"
            . "en-GB-Neural2-B|UK English Neural2 B";
    }
}
