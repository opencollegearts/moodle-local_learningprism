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

namespace local_learningprism\output;

use local_learningprism\manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Head HTML helper for AMD injection.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_helper {
    /** @var bool Prevent double init when both hook + legacy callback fire. */
    private static $initialised = false;

    /**
     * Inject course-page AMD when applicable.
     *
     * Must never throw — Open LMS/Whoops converts warnings/notices into exceptions
     * that leave the Snap loading overlay stuck ("ghosted" navigation).
     *
     * @return string Empty string (side-effect only).
     */
    public static function before_html_head(): string {
        global $PAGE, $COURSE;

        try {
            if (self::$initialised) {
                return '';
            }
            if (!manager::plugin_enabled()) {
                return '';
            }

            // Prefer $PAGE->course — more reliable than $COURSE during head output.
            $course = null;
            if (isset($PAGE) && $PAGE->course && !empty($PAGE->course->id)) {
                $course = $PAGE->course;
            } else if (!empty($COURSE) && is_object($COURSE) && !empty($COURSE->id)) {
                $course = $COURSE;
            }
            if (!$course) {
                return '';
            }
            $courseid = (int) $course->id;
            if ($courseid === (int) SITEID) {
                return '';
            }
            if (!manager::course_enabled($courseid)) {
                return '';
            }

            if (!self::is_course_ui_page($PAGE, $courseid)) {
                return '';
            }

            $context = \context_course::instance($courseid, IGNORE_MISSING);
            if (!$context) {
                return '';
            }
            if (!has_any_capability([
                'local/learningprism:request',
                'local/learningprism:download',
                'local/learningprism:manage',
            ], $context)) {
                return '';
            }

            // styles.css is auto-loaded by Moodle for local plugins; do not call requires->css().
            $canmanage = has_capability('local/learningprism:manage', $context);
            $PAGE->requires->js_call_amd('local_learningprism/course', 'init', [
                $courseid,
                $canmanage,
            ]);
            self::$initialised = true;
        } catch (\Throwable $e) {
            // Do not call debugging() — Open LMS/Whoops promotes notices to fatals.
            error_log('local_learningprism hook_helper: ' . $e->getMessage());
        }

        return '';
    }

    /**
     * Whether this looks like a course UI page where activity icons belong.
     *
     * Snap/Open LMS can fire before_standard_head_html_generation before
     * pagetype/pagelayout/url are populated — in that case fall back to context
     * / course id so AMD still loads. The JS is a no-op when no activity nodes exist.
     *
     * @param \moodle_page $page
     * @param int $courseid
     * @return bool
     */
    private static function is_course_ui_page($page, int $courseid): bool {
        $pagetype = isset($page->pagetype) ? (string) $page->pagetype : '';
        $pagelayout = isset($page->pagelayout) ? (string) $page->pagelayout : '';
        $pathname = '';
        if (isset($page->url) && $page->url instanceof \moodle_url) {
            $pathname = (string) $page->url->get_path();
        }

        if ($pagelayout === 'course' || $pagelayout === 'incourse') {
            return true;
        }
        if (strpos($pagetype, 'course-view') === 0 || strpos($pagetype, 'course-section') === 0) {
            return true;
        }
        if (strpos($pathname, '/course/view.php') !== false || strpos($pathname, '/course/section.php') !== false) {
            return true;
        }

        $ctxlevel = isset($page->context) ? (int) $page->context->contextlevel : 0;
        if ($ctxlevel === CONTEXT_COURSE || $ctxlevel === CONTEXT_MODULE) {
            return true;
        }

        // Head fired before Moodle finished setting page metadata.
        if ($pagetype === '' && $pagelayout === '' && $courseid > (int) SITEID) {
            return true;
        }

        return false;
    }
}
