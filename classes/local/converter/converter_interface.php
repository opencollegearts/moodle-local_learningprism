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

use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * Format converter contract.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface converter_interface {
    /**
     * @return string format key
     */
    public function get_format(): string;

    /**
     * @return bool
     */
    public function is_configured(): bool;

    /**
     * Convert normalised HTML into a stored file for the job.
     *
     * @param string $html
     * @param string $title
     * @param int $jobid
     * @param array $options
     * @return stored_file
     */
    public function convert(string $html, string $title, int $jobid, array $options = []): stored_file;
}
