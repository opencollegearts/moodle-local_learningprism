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

use context_module;
use local_learningprism\html_normalizer;
use local_learningprism\manager;
use moodle_exception;
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * Base activity content extractor.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class activity_module {
    /** @var \stdClass course module */
    protected $cm;

    /** @var context_module */
    protected $context;

    /** @var string|null */
    protected $htmlcache = null;

    /**
     * @param \stdClass $cm
     */
    public function __construct(\stdClass $cm) {
        $this->cm = $cm;
        $this->context = context_module::instance($cm->id);
    }

    /**
     * Activity display name.
     *
     * @return string
     */
    public function get_name(): string {
        return $this->cm->name;
    }

    /**
     * @return int
     */
    public function get_cmid(): int {
        return (int) $this->cm->id;
    }

    /**
     * @return int
     */
    public function get_courseid(): int {
        return (int) $this->cm->course;
    }

    /**
     * @return context_module
     */
    public function get_context(): context_module {
        return $this->context;
    }

    /**
     * Raw body HTML (without document wrapper).
     *
     * @return string
     */
    abstract protected function get_body_html(): string;

    /**
     * Full normalised HTML document.
     *
     * @return string
     * @throws moodle_exception
     */
    public function get_html(): string {
        if ($this->htmlcache !== null) {
            return $this->htmlcache;
        }
        $course = get_course($this->cm->course);
        $lang = $course->lang ?: current_language();
        $body = $this->get_body_html();
        $body = $this->rewrite_pluginfile_urls($body);
        $html = html_normalizer::normalise($this->get_name(), $body, $lang);

        $max = (int) get_config('local_learningprism', 'maxcontentlength');
        if ($max > 0 && \core_text::strlen(html_to_text($html, 0, false)) > $max) {
            throw new moodle_exception('error:contenttoolong', 'local_learningprism');
        }

        $this->htmlcache = $html;
        return $html;
    }

    /**
     * @return string
     */
    public function get_content_hash(): string {
        return html_normalizer::content_hash($this->get_html());
    }

    /**
     * Encode images as data URIs where possible.
     *
     * @param stored_file[] $files
     * @param string $html
     * @param string $filearea
     * @return string
     */
    protected function encode_images(array $files, string $html, string $filearea): string {
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            if (strpos((string) $mimetype, 'image/') !== 0) {
                continue;
            }
            $patterns = [
                '@@PLUGINFILE@@/' . rawurlencode($filename),
                '@@PLUGINFILE@@/' . $filename,
            ];
            $data = 'data:' . $mimetype . ';base64,' . base64_encode($file->get_content());
            foreach ($patterns as $pattern) {
                $html = str_replace($pattern, $data, $html);
            }
        }
        return $html;
    }

    /**
     * Leave unresolved pluginfile tokens as empty / strip broken src when needed.
     *
     * @param string $html
     * @return string
     */
    protected function rewrite_pluginfile_urls(string $html): string {
        // Already inlined where possible; strip remaining pluginfile placeholders that would break converters.
        return preg_replace('/src=["\']@@PLUGINFILE@@[^"\']*["\']/i', 'src=""', $html) ?? $html;
    }
}
