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

use local_learningprism\local\converter\converter_factory;
use local_learningprism\local\mod\module_factory;

defined('MOODLE_INTERNAL') || die();

/**
 * Conversion job lifecycle.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_manager {
    /**
     * @param int $jobid
     * @return \stdClass|null
     */
    public static function get_job(int $jobid): ?\stdClass {
        global $DB;
        $job = $DB->get_record('local_learningprism_job', ['id' => $jobid]);
        return $job ?: null;
    }

    /**
     * Aggregate button state for a CM + content hash.
     *
     * @param int $cmid
     * @param string $contenthash
     * @return string request|processing|download|error
     */
    public static function button_state(int $cmid, string $contenthash): string {
        global $DB;

        $jobs = $DB->get_records('local_learningprism_job', [
            'cmid' => $cmid,
            'contenthash' => $contenthash,
            'personal' => 0,
        ]);
        if (!$jobs) {
            return 'request';
        }
        $hasready = false;
        $hasprocessing = false;
        $haserror = false;
        foreach ($jobs as $job) {
            if ($job->status === manager::STATUS_READY) {
                $hasready = true;
            } else if ($job->status === manager::STATUS_QUEUED || $job->status === manager::STATUS_PROCESSING) {
                $hasprocessing = true;
            } else if ($job->status === manager::STATUS_ERROR) {
                $haserror = true;
            }
        }
        if ($hasprocessing) {
            return 'processing';
        }
        if ($hasready) {
            return 'download';
        }
        if ($haserror) {
            return 'error';
        }
        return 'request';
    }

    /**
     * Queue formats for an activity (request or pregenerate).
     *
     * Existing error jobs are always re-queued. Ready jobs are left as-is unless
     * $forceregenerate is true (teachers regenerating a file).
     *
     * @param int $cmid
     * @param string[] $formats
     * @param int $userid
     * @param bool $forceregenerate Re-queue ready files as well as errors/missing.
     * @param bool $personal User-only print version (colour/font).
     * @param array $printoptions printbackground / font keys when $personal
     * @return \stdClass[] jobs
     */
    public static function queue_formats(
        int $cmid,
        array $formats,
        int $userid,
        bool $forceregenerate = false,
        bool $personal = false,
        array $printoptions = []
    ): array {
        global $DB;

        $module = module_factory::instance_from_cmid($cmid);
        $hash = $module->get_content_hash();
        $courseid = $module->get_courseid();
        $enabled = manager::enabled_formats();
        $now = time();
        $jobs = [];

        if ($personal) {
            $printoptions = html_normalizer::save_user_print_options($userid, $printoptions);
        } else {
            $printoptions = [];
        }
        $optionshash = $personal ? html_normalizer::print_options_hash($printoptions) : '';

        $cmjobs = $DB->get_records('local_learningprism_job', ['cmid' => $cmid]);
        $exactjobs = [];
        $sharedfallback = [];
        foreach ($cmjobs as $cmjob) {
            $ispersonal = (int) $cmjob->personal === 1;
            if ($personal) {
                if ($ispersonal
                        && (int) $cmjob->userid === $userid
                        && $cmjob->contenthash === $hash
                        && $cmjob->optionshash === $optionshash) {
                    $exactjobs[$cmjob->format] = $cmjob;
                }
                continue;
            }
            if ($ispersonal) {
                continue;
            }
            if ($cmjob->contenthash === $hash) {
                $exactjobs[$cmjob->format] = $cmjob;
            }
            if (!isset($sharedfallback[$cmjob->format])
                    || (int) $cmjob->timemodified > (int) $sharedfallback[$cmjob->format]->timemodified) {
                $sharedfallback[$cmjob->format] = $cmjob;
            }
        }

        foreach ($formats as $format) {
            if (!in_array($format, $enabled, true)) {
                continue;
            }
            if ($personal && !manager::is_print_format($format)) {
                continue;
            }

            $existing = $exactjobs[$format] ?? false;
            if (!$existing && !$personal) {
                $existing = $sharedfallback[$format] ?? false;
            }
            if ($existing) {
                $requeued = self::requeue_job($existing, $userid, $forceregenerate);
                $jobs[] = $requeued ?? $existing;
                continue;
            }

            $provider = null;
            if ($format === manager::FORMAT_AUDIO) {
                $provider = get_config('local_learningprism', 'ttsprovider') ?: 'google';
            } else if ($format === manager::FORMAT_PDF) {
                $provider = get_config('local_learningprism', 'pdfengine') ?: 'none';
            } else if ($format === manager::FORMAT_DOCX) {
                $provider = 'phpword';
            } else if ($format === manager::FORMAT_EPUB) {
                $provider = 'epub';
            }

            $record = (object) [
                'cmid' => $cmid,
                'courseid' => $courseid,
                'contenthash' => $hash,
                'format' => $format,
                'provider' => $provider,
                'status' => manager::STATUS_QUEUED,
                'fileid' => null,
                'options' => json_encode($printoptions),
                'errorinfo' => null,
                'userid' => $userid,
                'personal' => $personal ? 1 : 0,
                'optionshash' => $optionshash,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $record->id = $DB->insert_record('local_learningprism_job', $record);
            self::log_request((int) $record->id, $userid, manager::REQUEST_TYPE_REQUEST);
            $jobs[] = $record;
        }

        // Attempt immediate local zip conversions (DOCX / EPUB).
        $processedids = [];
        foreach ($jobs as $job) {
            if (in_array($job->format, [manager::FORMAT_DOCX, manager::FORMAT_EPUB], true)
                    && $job->status === manager::STATUS_QUEUED) {
                try {
                    self::process_job($job);
                    $processedids[] = (int) $job->id;
                } catch (\Throwable $e) {
                    // Leave queued for cron. Avoid debugging() — Whoops promotes it to fatal.
                    error_log('local_learningprism queue local: ' . $e->getMessage());
                }
            }
        }
        if ($processedids) {
            $freshjobs = $DB->get_records_list('local_learningprism_job', 'id', $processedids);
            foreach ($jobs as $index => $job) {
                $id = (int) $job->id;
                if (isset($freshjobs[$id])) {
                    $jobs[$index] = $freshjobs[$id];
                }
            }
        }

        return $jobs;
    }

    /**
     * Re-queue an existing job so cron will convert it again.
     *
     * Skips jobs that are already queued or processing. Ready jobs are only
     * re-queued when $force is true. Keeps any previous file until the new
     * conversion succeeds so downloads still work during regeneration.
     *
     * @param \stdClass $job
     * @param int $userid
     * @param bool $force Also regenerate a ready file
     * @return \stdClass|null Updated job, or null if skipped
     */
    public static function requeue_job(\stdClass $job, int $userid, bool $force = false): ?\stdClass {
        global $DB;

        $job = self::get_job((int) $job->id);
        if (!$job) {
            return null;
        }

        if ($job->status === manager::STATUS_QUEUED || $job->status === manager::STATUS_PROCESSING) {
            self::log_request((int) $job->id, $userid, manager::REQUEST_TYPE_REQUEST);
            return $job;
        }

        $canrequeue = ($job->status === manager::STATUS_ERROR)
            || ($force && $job->status === manager::STATUS_READY);
        if (!$canrequeue) {
            self::log_request((int) $job->id, $userid, manager::REQUEST_TYPE_REQUEST);
            return $job;
        }

        try {
            $module = module_factory::instance_from_cmid((int) $job->cmid);
            $job->contenthash = $module->get_content_hash();
            $job->courseid = $module->get_courseid();
        } catch (\Throwable $e) {
            error_log('local_learningprism requeue: ' . $e->getMessage());
        }

        $job->status = manager::STATUS_QUEUED;
        $job->errorinfo = null;
        $job->userid = $userid;
        $job->timemodified = time();
        $DB->update_record('local_learningprism_job', $job);
        self::log_request((int) $job->id, $userid, manager::REQUEST_TYPE_REQUEST);

        return $job;
    }

    /**
     * Process a single job.
     *
     * @param \stdClass $job
     */
    public static function process_job(\stdClass $job): void {
        global $DB;

        $job = self::get_job((int) $job->id);
        if (!$job || ($job->status !== manager::STATUS_QUEUED && $job->status !== manager::STATUS_ERROR)) {
            return;
        }

        $job->status = manager::STATUS_PROCESSING;
        $job->timemodified = time();
        $DB->update_record('local_learningprism_job', $job);

        try {
            $module = module_factory::instance_from_cmid((int) $job->cmid);
            $html = $module->get_html();
            // Identity is the module hash stored at queue time (HTML for Book/Page/Label,
            // Rise runtime-data.js for SCORM). Do not re-hash the converted HTML.
            if ($module->get_content_hash() !== $job->contenthash) {
                throw new \moodle_exception('modal:stale', 'local_learningprism');
            }

            $options = json_decode($job->options ?? '{}', true) ?: [];
            if (!empty($job->personal)) {
                $options = html_normalizer::normalise_print_options($options);
                $html = html_normalizer::apply_print_style($html, $options);
            }

            $converter = converter_factory::for_format($job->format);
            $result = $converter->convert($html, $module->get_name(), (int) $job->id, $options);

            $job->fileid = $result->get_id();
            $job->status = manager::STATUS_READY;
            $job->errorinfo = null;
            $job->timemodified = time();
            $DB->update_record('local_learningprism_job', $job);
        } catch (\Throwable $e) {
            $job = self::get_job((int) $job->id) ?? $job;
            $errormessage = get_string('error:conversionfailed', 'local_learningprism');
            if ($e instanceof \moodle_exception && $e->errorcode !== 'error:conversionfailed') {
                $errormessage = $e->getMessage();
            }
            error_log('local_learningprism process_job ' . (int) $job->id . ': ' . $e->getMessage());
            $job->status = manager::STATUS_ERROR;
            $job->errorinfo = $errormessage;
            $job->timemodified = time();
            $DB->update_record('local_learningprism_job', $job);
            try {
                notifier::notify_failure($job, $errormessage);
            } catch (\Throwable $notifyerror) {
                error_log('local_learningprism notify: ' . $notifyerror->getMessage());
            }
            return;
        }

        // Never let messaging failures (e.g. invalid noreply@localhost under Whoops)
        // overwrite a successful READY status.
        try {
            notifier::notify_success($job, $module->get_name());
        } catch (\Throwable $notifyerror) {
            error_log('local_learningprism notify: ' . $notifyerror->getMessage());
        }
    }

    /**
     * Cron batch processor.
     *
     * @return int number processed
     */
    public static function process_queued_jobs(): int {
        global $DB;

        $limit = (int) get_config('local_learningprism', 'jobbatchlimit') ?: 5;
        $jobs = $DB->get_records('local_learningprism_job', ['status' => manager::STATUS_QUEUED], 'timecreated ASC', '*', 0, $limit);
        $count = 0;
        foreach ($jobs as $job) {
            try {
                self::process_job($job);
                $count++;
            } catch (\Throwable $e) {
                // Already marked error inside process_job.
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param int $jobid
     * @param int $userid
     * @param string $type
     */
    public static function log_request(int $jobid, int $userid, string $type): void {
        global $DB;
        $DB->insert_record('local_learningprism_request', (object) [
            'jobid' => $jobid,
            'userid' => $userid,
            'type' => $type,
            'timecreated' => time(),
        ]);
    }

    /**
     * Format statuses for one activity.
     *
     * @param int $cmid
     * @return array
     */
    public static function get_activity_format_statuses(int $cmid): array {
        global $DB, $USER;

        $module = module_factory::instance_from_cmid($cmid);
        $hash = $module->get_content_hash();
        $enabled = manager::enabled_formats();
        $printoptions = html_normalizer::user_print_options((int) ($USER->id ?? 0));
        $optionshash = html_normalizer::print_options_hash($printoptions);
        $jobs = $DB->get_records('local_learningprism_job', [
            'cmid' => $cmid,
            'contenthash' => $hash,
        ]);
        $sharedjobs = [];
        $personaljobs = [];
        foreach ($jobs as $loaded) {
            if ((int) $loaded->personal === 0) {
                $sharedjobs[$loaded->format] = $loaded;
            } else if (!empty($USER->id)
                    && (int) $loaded->userid === (int) $USER->id
                    && $loaded->optionshash === $optionshash) {
                $personaljobs[$loaded->format] = $loaded;
            }
        }
        $result = [];
        foreach ($enabled as $format) {
            $job = $sharedjobs[$format] ?? null;
            $item = [
                'format' => $format,
                'label' => get_string('format:' . $format, 'local_learningprism'),
                'status' => $job ? $job->status : 'none',
                'jobid' => $job ? (int) $job->id : 0,
                'errorinfo' => $job->errorinfo ?? '',
                'downloadurl' => '',
                'supportspersonal' => manager::is_print_format($format),
                'personalstatus' => 'none',
                'personaljobid' => 0,
                'personalerrorinfo' => '',
                'personaldownloadurl' => '',
            ];
            if ($job && $job->status === manager::STATUS_READY) {
                $url = filemanager::get_download_url($job);
                $item['downloadurl'] = $url ? $url->out(false) : '';
            }
            if ($item['supportspersonal'] && !empty($USER->id)) {
                $pjob = $personaljobs[$format] ?? null;
                if ($pjob) {
                    $item['personalstatus'] = $pjob->status;
                    $item['personaljobid'] = (int) $pjob->id;
                    $item['personalerrorinfo'] = $pjob->errorinfo ?? '';
                    if ($pjob->status === manager::STATUS_READY) {
                        $purl = filemanager::get_download_url($pjob);
                        $item['personaldownloadurl'] = $purl ? $purl->out(false) : '';
                    }
                }
            }
            $result[] = $item;
        }

        $backgrounds = [];
        foreach (html_normalizer::print_background_presets() as $key => $hex) {
            $backgrounds[] = [
                'key' => $key,
                'label' => get_string('settings:printbackground_' . $key, 'local_learningprism'),
                'selected' => $key === $printoptions['printbackground'],
            ];
        }
        $fonts = [];
        foreach (array_keys(html_normalizer::print_font_presets()) as $key) {
            $fonts[] = [
                'key' => $key,
                'label' => get_string('settings:font_' . $key, 'local_learningprism'),
                'selected' => $key === $printoptions['font'],
            ];
        }

        return [
            'cmid' => $cmid,
            'name' => $module->get_name(),
            'contenthash' => $hash,
            'state' => self::button_state($cmid, $hash),
            'printbackground' => $printoptions['printbackground'],
            'font' => $printoptions['font'],
            'backgrounds' => $backgrounds,
            'fonts' => $fonts,
            'formats' => $result,
        ];
    }

    /**
     * Whether the given user may download this job's file.
     *
     * @param \stdClass $job
     * @param int $userid
     * @return bool
     */
    public static function user_can_access_job(\stdClass $job, int $userid): bool {
        if (empty($job->personal)) {
            return true;
        }
        return (int) $job->userid === $userid;
    }

    /**
     * Purge stale completed jobs older than configured days when hash no longer current.
     *
     * @return int
     */
    public static function purge_stale(): int {
        global $DB;

        $days = (int) get_config('local_learningprism', 'staledays') ?: 30;
        $before = time() - ($days * DAYSECS);
        $jobs = $DB->get_records_select(
            'local_learningprism_job',
            'status = :ready AND timemodified < :before',
            ['ready' => manager::STATUS_READY, 'before' => $before]
        );
        $purged = 0;
        foreach ($jobs as $job) {
            try {
                $module = module_factory::instance_from_cmid((int) $job->cmid);
                if ($module->get_content_hash() === $job->contenthash) {
                    continue;
                }
            } catch (\Throwable $e) {
                // Module gone — purge.
            }
            filemanager::delete_job_files((int) $job->id);
            $DB->delete_records('local_learningprism_request', ['jobid' => $job->id]);
            $DB->delete_records('local_learningprism_job', ['id' => $job->id]);
            $purged++;
        }
        return $purged;
    }
}
