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

namespace local_learningprism\local\mod;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Label (text and media) activity extractor.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_label extends activity_module {
    /**
     * @return string
     * @throws moodle_exception
     */
    protected function get_body_html(): string {
        global $DB;

        $sql = "SELECT l.id, l.name, l.intro
                  FROM {label} l
                  JOIN {course_modules} cm ON cm.instance = l.id
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name = :name AND cm.id = :cmid";
        $label = $DB->get_record_sql($sql, ['name' => 'label', 'cmid' => $this->cm->id]);
        if (!$label) {
            throw new moodle_exception('moduledoesnotexist');
        }

        $fs = get_file_storage();
        $intro = '<div id="label-content">' . $label->intro . '</div>';
        $introfiles = $fs->get_area_files($this->context->id, 'mod_label', 'intro');
        return $this->encode_images($introfiles, $intro, 'intro');
    }
}
