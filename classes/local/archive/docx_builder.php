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

namespace local_learningprism\local\archive;

use local_learningprism\filemanager;
use local_learningprism\html_normalizer;
use local_learningprism\local\converter\docx_converter;

defined('MOODLE_INTERNAL') || die();

/**
 * Build DOCX files for section / assign / forum archive items.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class docx_builder {
    /**
     * Build and store DOCX for an archive item that is not a native file or converted activity.
     *
     * @param \stdClass $row Archive item row
     * @param array $source Collector source
     * @param \stdClass $course
     * @return \stored_file|null
     */
    public static function ensure_stored(\stdClass $row, array $source, \stdClass $course): ?\stored_file {
        $existing = filemanager::get_archive_file((int) $row->id);
        if ($existing && $row->contenthash === $source['contenthash']) {
            return $existing;
        }

        $html = '';
        $title = pathinfo($source['displayname'], PATHINFO_FILENAME);
        if ($source['sourcetype'] === collector::TYPE_SECTION) {
            $modinfo = get_fast_modinfo($course);
            $section = $modinfo->get_section_info((int) $source['sectionnum']);
            if (!$section) {
                return null;
            }
            $html = collector::section_summary_html($course, $section);
            $title = get_string('archive:sectionoverview', 'local_learningprism');
        } else if ($source['sourcetype'] === collector::TYPE_ASSIGN) {
            $cm = get_fast_modinfo($course)->cms[$source['cmid']] ?? null;
            if (!$cm) {
                return null;
            }
            $html = self::assign_html($cm);
            $title = $cm->name;
        } else if ($source['sourcetype'] === collector::TYPE_FORUM) {
            $cm = get_fast_modinfo($course)->cms[$source['cmid']] ?? null;
            if (!$cm) {
                return null;
            }
            $html = self::forum_html($cm);
            $title = $cm->name;
        } else {
            return null;
        }

        if ($html === '') {
            return null;
        }

        $converter = new docx_converter();
        $binary = $converter->build_docx($html, $title);
        $filename = docx_converter::safe_filename($title) . '.docx';
        return filemanager::store_archive_file((int) $row->id, $filename, $binary);
    }

    /**
     * @param \cm_info $cm
     * @return string
     */
    public static function assign_html($cm): string {
        global $DB;
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $course = get_course($cm->course);
        $lang = $course->lang ?: current_language();

        $parts = [];
        $parts[] = '<h1>' . s($assign->name) . '</h1>';

        $intro = trim((string) $assign->intro);
        if ($intro !== '') {
            $parts[] = '<h2>' . get_string('description') . '</h2>';
            $parts[] = format_module_intro('assign', $assign, $cm->id);
        }

        if (property_exists($assign, 'activity')) {
            $activity = trim((string) $assign->activity);
            if ($activity !== '' && trim(html_to_text($activity, 0, false)) !== '') {
                $activity = file_rewrite_pluginfile_urls(
                    $activity,
                    'pluginfile.php',
                    $context->id,
                    'mod_assign',
                    'activity',
                    0
                );
                $heading = get_string_manager()->string_exists('activityeditor', 'mod_assign')
                    ? get_string('activityeditor', 'mod_assign')
                    : get_string('description');
                $parts[] = '<h2>' . $heading . '</h2>';
                $parts[] = format_text(
                    $activity,
                    $assign->activityformat ?? FORMAT_HTML,
                    ['context' => $context, 'noclean' => true]
                );
            }
        }

        $body = implode("\n", $parts);
        return html_normalizer::normalise($assign->name, $body, $lang);
    }

    /**
     * @param \cm_info $cm
     * @return string
     */
    public static function forum_html($cm): string {
        global $DB;
        $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = get_course($cm->course);
        $lang = $course->lang ?: current_language();

        $parts = [];
        $parts[] = '<h1>' . s($forum->name) . '</h1>';
        $intro = trim((string) $forum->intro);
        if ($intro !== '') {
            $parts[] = '<h2>' . get_string('description') . '</h2>';
            $parts[] = format_module_intro('forum', $forum, $cm->id);
        }
        $body = implode("\n", $parts);
        return html_normalizer::normalise($forum->name, $body, $lang);
    }
}
