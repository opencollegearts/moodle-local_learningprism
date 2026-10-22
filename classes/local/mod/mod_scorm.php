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
use stored_file;

defined('MOODLE_INTERNAL') || die();

/**
 * SCORM activity extractor (Articulate Rise packages).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_scorm extends activity_module {
    /** @var stored_file[]|null filename => file */
    private $fileindex = null;

    /**
     * Hash the Rise runtime payload so course-page listing does not inline images.
     *
     * @return string
     * @throws moodle_exception
     */
    public function get_content_hash(): string {
        $runtime = $this->find_runtime_file();
        if (!$runtime) {
            throw new moodle_exception('error:scormnotrise', 'local_learningprism');
        }
        return $runtime->get_contenthash();
    }

    /**
     * @return string
     * @throws moodle_exception
     */
    protected function get_body_html(): string {
        $runtime = $this->find_runtime_file();
        if (!$runtime) {
            throw new moodle_exception('error:scormnotrise', 'local_learningprism');
        }
        $data = rise_extractor::decode($runtime->get_content());
        if ($data === null) {
            throw new moodle_exception('error:scormnotrise', 'local_learningprism');
        }

        $index = $this->file_index();
        $resolvemedia = function (array $ref) use ($index): string {
            foreach (rise_extractor::media_filenames($ref) as $name) {
                $file = $this->lookup_file($index, $name);
                if (!$file) {
                    continue;
                }
                $mimetype = $file->get_mimetype();
                if (strpos((string) $mimetype, 'image/') !== 0) {
                    continue;
                }
                return 'data:' . $mimetype . ';base64,' . base64_encode($file->get_content());
            }
            return '';
        };

        return rise_extractor::to_html($data, $resolvemedia);
    }

    /**
     * @return stored_file|null
     */
    private function find_runtime_file(): ?stored_file {
        $fs = get_file_storage();
        foreach ([
            ['/scormcontent/', 'runtime-data.js'],
            ['/', 'runtime-data.js'],
        ] as $try) {
            $file = $fs->get_file($this->context->id, 'mod_scorm', 'content', 0, $try[0], $try[1]);
            if ($file && !$file->is_directory()) {
                return $file;
            }
        }
        foreach ($this->file_index() as $file) {
            if ($file->get_filename() === 'runtime-data.js') {
                return $file;
            }
        }
        return null;
    }

    /**
     * @return stored_file[]
     */
    private function file_index(): array {
        if ($this->fileindex !== null) {
            return $this->fileindex;
        }
        $fs = get_file_storage();
        $files = $fs->get_area_files($this->context->id, 'mod_scorm', 'content', 0, 'filepath, filename', false);
        $this->fileindex = [];
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $this->fileindex[] = $file;
        }
        return $this->fileindex;
    }

    /**
     * @param stored_file[] $index
     * @param string $name
     * @return stored_file|null
     */
    private function lookup_file(array $index, string $name): ?stored_file {
        $want = strtolower($name);
        $best = null;
        $bestscore = -1;
        foreach ($index as $file) {
            if (strtolower($file->get_filename()) !== $want) {
                continue;
            }
            $path = strtolower($file->get_filepath());
            $score = 0;
            if (strpos($path, '/assets/') !== false) {
                $score += 2;
            }
            if (strpos($path, 'scormcontent') !== false) {
                $score += 1;
            }
            if ($score > $bestscore) {
                $best = $file;
                $bestscore = $score;
            }
        }
        return $best;
    }
}
