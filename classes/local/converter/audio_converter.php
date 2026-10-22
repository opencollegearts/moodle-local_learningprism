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

namespace local_learningprism\local\converter;

use local_learningprism\filemanager;
use local_learningprism\html_normalizer;
use local_learningprism\local\tts\tts_factory;
use local_learningprism\manager;
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * HTML to TTS audio converter.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audio_converter implements converter_interface {
    public function get_format(): string {
        return manager::FORMAT_AUDIO;
    }

    public function is_configured(): bool {
        try {
            tts_factory::instance();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function convert(string $html, string $title, int $jobid, array $options = []): stored_file {
        $text = html_normalizer::to_speech_text($html);
        if (trim($text) === '') {
            throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
        }
        $provider = tts_factory::instance();
        $audio = $provider->synthesize($text, $options);
        $filename = docx_converter::safe_filename($title) . '.mp3';
        return filemanager::store_converted_file($jobid, $filename, 'audio/mpeg', $audio);
    }
}
