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

namespace local_learningprism;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle messaging for job completion/failure.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /**
     * @param \stdClass $job
     * @param string $activityname
     */
    public static function notify_success(\stdClass $job, string $activityname): void {
        self::send($job, 'success', [
            'format' => get_string('format:' . $job->format, 'local_learningprism'),
            'name' => $activityname,
        ]);
    }

    /**
     * @param \stdClass $job
     * @param string $error
     */
    public static function notify_failure(\stdClass $job, string $error): void {
        $name = '';
        try {
            $cm = get_coursemodule_from_id(null, $job->cmid);
            $name = $cm ? $cm->name : '';
        } catch (\Throwable $e) {
            $name = '';
        }
        self::send($job, 'failure', [
            'format' => get_string('format:' . $job->format, 'local_learningprism'),
            'name' => $name,
            'error' => $error,
        ]);
    }

    /**
     * @param \stdClass $job
     * @param string $provider
     * @param array $a
     */
    private static function send(\stdClass $job, string $provider, array $a): void {
        global $DB, $CFG;

        try {
            $user = $DB->get_record('user', ['id' => $job->userid, 'deleted' => 0]);
            if (!$user) {
                return;
            }

            // Local/dev sites often have invalid noreply@localhost. Whoops turns
            // Moodle's debugging() about that into exceptions — never let that
            // break conversion success handling.
            $noreply = !empty($CFG->noreplyaddress) ? (string) $CFG->noreplyaddress : '';
            if ($noreply === '' || stripos($noreply, '@localhost') !== false || !validate_email($noreply)) {
                error_log('local_learningprism: skip email notify (invalid noreplyaddress)');
                return;
            }

            $message = new \core\message\message();
            $message->component = 'local_learningprism';
            $message->name = $provider;
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $user;
            $message->subject = get_string('message:' . $provider . ':subject', 'local_learningprism');
            $message->fullmessage = get_string('message:' . $provider . ':body', 'local_learningprism', (object) $a);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = nl2br(s($message->fullmessage));
            $message->smallmessage = $message->subject;
            $message->notification = 1;
            $message->contexturl = (new \moodle_url('/course/view.php', ['id' => $job->courseid]))->out(false);
            $message->contexturlname = get_string('pluginname', 'local_learningprism');

            message_send($message);
        } catch (\Throwable $e) {
            error_log('local_learningprism notifier: ' . $e->getMessage());
        }
    }
}
