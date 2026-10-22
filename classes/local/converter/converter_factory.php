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

use local_learningprism\manager;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolve converters by format.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class converter_factory {
    /**
     * @param string $format
     * @return converter_interface
     * @throws moodle_exception
     */
    public static function for_format(string $format): converter_interface {
        switch ($format) {
            case manager::FORMAT_DOCX:
                $converter = new docx_converter();
                break;
            case manager::FORMAT_PDF:
                $converter = new pdf_converter();
                break;
            case manager::FORMAT_EPUB:
                $converter = new epub_converter();
                break;
            case manager::FORMAT_AUDIO:
                $converter = new audio_converter();
                break;
            default:
                throw new moodle_exception('error:unsupportedmodule', 'local_learningprism');
        }
        if (!$converter->is_configured()) {
            if ($format === manager::FORMAT_PDF) {
                throw new moodle_exception('error:pdfnotconfigured', 'local_learningprism');
            }
            if ($format === manager::FORMAT_AUDIO) {
                throw new moodle_exception('error:ttsnotconfigured', 'local_learningprism');
            }
        }
        return $converter;
    }
}
