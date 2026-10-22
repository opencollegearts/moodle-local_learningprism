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
 * TTS provider contract.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface tts_provider_interface {
    /**
     * @return bool
     */
    public function is_configured(): bool;

    /**
     * Synthesize speech audio (mp3 bytes) from plain text.
     *
     * @param string $text
     * @param array $options
     * @return string binary audio
     */
    public function synthesize(string $text, array $options = []): string;
}
