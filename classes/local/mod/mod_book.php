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
 * Book activity extractor.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_book extends activity_module {
    /**
     * @return string
     * @throws moodle_exception
     */
    protected function get_body_html(): string {
        global $DB;

        $sql = "SELECT b.id, b.name, b.intro
                  FROM {book} b
                  JOIN {course_modules} cm ON cm.instance = b.id
                  JOIN {modules} m ON m.id = cm.module
                 WHERE m.name = :name AND cm.id = :cmid";
        $book = $DB->get_record_sql($sql, ['name' => 'book', 'cmid' => $this->cm->id]);
        if (!$book) {
            throw new moodle_exception('moduledoesnotexist');
        }

        $fs = get_file_storage();
        $html = '';
        if (!empty($book->intro)) {
            $intro = '<div id="intro">' . $book->intro . '</div>';
            $introfiles = $fs->get_area_files($this->context->id, 'mod_book', 'intro');
            $html .= $this->encode_images($introfiles, $intro, 'intro');
        }

        $canviewhidden = has_capability('mod/book:viewhiddenchapters', $this->context);
        $chapters = $DB->get_records('book_chapters', ['bookid' => $book->id], 'pagenum ASC');
        foreach ($chapters as $chapter) {
            if (!$canviewhidden && !empty($chapter->hidden)) {
                continue;
            }
            $level = max(2, min(6, 1 + (int) $chapter->subchapter));
            $html .= '<h' . $level . '>' . format_string($chapter->title) . '</h' . $level . '>';
            $chapterhtml = '<div class="chapter" data-chapterid="' . (int) $chapter->id . '">' .
                $chapter->content . '</div>';
            $chapterfiles = $fs->get_area_files($this->context->id, 'mod_book', 'chapter', $chapter->id);
            $html .= $this->encode_images($chapterfiles, $chapterhtml, 'chapter');
        }

        return $html;
    }
}
