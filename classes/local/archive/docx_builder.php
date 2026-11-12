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
use local_learningprism\local\converter\epub_converter;
use local_learningprism\local\converter\pdf_converter;
use local_learningprism\local\tts\tts_factory;
use local_learningprism\local\tts\voice_catalog;
use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Build converted files for section / assign / forum archive items.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class docx_builder {
    /**
     * Build and store a converted file for an archive item that is not a native file or activity job.
     *
     * @param \stdClass $row Archive item row
     * @param array $source Collector source
     * @param \stdClass $course
     * @return \stored_file|null
     */
    public static function ensure_stored(\stdClass $row, array $source, \stdClass $course): ?\stored_file {
        $format = (string) ($source['format'] ?? manager::FORMAT_DOCX);
        $existing = filemanager::get_archive_file((int) $row->id);
        if ($existing && $row->contenthash === $source['contenthash']) {
            return $existing;
        }

        $html = self::source_html($source, $course);
        if ($html === null || $html === '') {
            return null;
        }

        $title = pathinfo($source['displayname'], PATHINFO_FILENAME);
        if ($source['sourcetype'] === collector::TYPE_SECTION) {
            $title = get_string('archive:sectionoverview', 'local_learningprism');
        }

        $binary = self::convert_html($html, $title, $format, (int) $course->id);
        if ($binary === null || $binary === '') {
            return null;
        }

        $filename = docx_converter::safe_filename($title) . self::extension_for_format($format);
        return filemanager::store_archive_file((int) $row->id, $filename, $binary);
    }

    /**
     * @param array $source
     * @param \stdClass $course
     * @return string|null
     */
    public static function source_html(array $source, \stdClass $course): ?string {
        if ($source['sourcetype'] === collector::TYPE_SECTION) {
            $modinfo = get_fast_modinfo($course);
            $section = $modinfo->get_section_info((int) $source['sectionnum']);
            if (!$section) {
                return null;
            }
            return collector::section_summary_html($course, $section);
        }
        if ($source['sourcetype'] === collector::TYPE_ASSIGN) {
            $cm = get_fast_modinfo($course)->cms[$source['cmid']] ?? null;
            return $cm ? self::assign_html($cm) : null;
        }
        if ($source['sourcetype'] === collector::TYPE_FORUM) {
            $cm = get_fast_modinfo($course)->cms[$source['cmid']] ?? null;
            return $cm ? self::forum_html($cm) : null;
        }
        return null;
    }

    /**
     * @param string $html
     * @param string $title
     * @param string $format
     * @param int $courseid
     * @return string|null
     */
    private static function convert_html(string $html, string $title, string $format, int $courseid): ?string {
        if ($format === manager::FORMAT_DOCX) {
            $converter = new docx_converter();
            return $converter->build_docx($html, $title);
        }
        if ($format === manager::FORMAT_EPUB) {
            $converter = new epub_converter();
            return $converter->build_epub($html, $title);
        }
        if ($format === manager::FORMAT_PDF) {
            $converter = new pdf_converter();
            if (!$converter->is_configured()) {
                throw new \moodle_exception('error:pdfnotconfigured', 'local_learningprism');
            }
            return $converter->build_pdf($html, $title, 0);
        }
        if ($format === manager::FORMAT_AUDIO) {
            $text = html_normalizer::to_speech_text($html);
            if (trim($text) === '') {
                throw new \moodle_exception('error:conversionfailed', 'local_learningprism');
            }
            $provider = tts_factory::instance();
            $options = ['voice' => voice_catalog::course_default($courseid)];
            return $provider->synthesize($text, $options);
        }
        return null;
    }

    /**
     * @param string $format
     * @return string
     */
    private static function extension_for_format(string $format): string {
        return match ($format) {
            manager::FORMAT_PDF => '.pdf',
            manager::FORMAT_EPUB => '.epub',
            manager::FORMAT_AUDIO => '.mp3',
            default => '.docx',
        };
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
