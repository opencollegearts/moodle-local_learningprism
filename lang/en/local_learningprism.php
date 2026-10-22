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

/**
 * English language strings for local_learningprism.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'OCA Learning Prism';
$string['learningprism'] = 'Learning Prism';

$string['learningprism:manage'] = 'Manage Learning Prism for a course';
$string['learningprism:request'] = 'Request alternate formats';
$string['learningprism:download'] = 'Download alternate formats';
$string['learningprism:viewreport'] = 'View Learning Prism reports';

$string['settings:heading'] = 'OCA Learning Prism settings';
$string['settings:enable'] = 'Enable Learning Prism';
$string['settings:enable_desc'] = 'Master switch for the Learning Prism plugin site-wide.';
$string['settings:enabledocx'] = 'Enable DOCX format';
$string['settings:enabledocx_desc'] = 'Allow generation of Word (.docx) documents from activity HTML.';
$string['settings:enablepdf'] = 'Enable PDF format';
$string['settings:enablepdf_desc'] = 'Allow generation of PDF documents. Requires a configured PDF engine.';
$string['settings:enableepub'] = 'Enable EPUB format';
$string['settings:enableepub_desc'] = 'Allow generation of EPUB 3 ebooks from activity HTML. Built in PHP; no extra service required.';
$string['settings:enableaudio'] = 'Enable TTS audio format';
$string['settings:enableaudio_desc'] = 'Allow generation of text-to-speech audio.';
$string['settings:printbackground'] = 'Print background colour';
$string['settings:printbackground_desc'] = 'Page background for PDF, EPUB, and Word. Light overlays only; body text stays dark. Change colour then regenerate existing files.';
$string['settings:printbackground_white'] = 'White (default)';
$string['settings:printbackground_cream'] = 'Cream';
$string['settings:printbackground_yellow'] = 'Pale yellow';
$string['settings:printbackground_blue'] = 'Pale blue';
$string['settings:printbackground_green'] = 'Pale green';
$string['settings:printbackground_pink'] = 'Pale pink';
$string['settings:font'] = 'Font';
$string['settings:font_default'] = 'Site default (Georgia)';
$string['settings:font_arial'] = 'Arial';
$string['settings:font_verdana'] = 'Verdana';
$string['settings:font_tahoma'] = 'Tahoma';
$string['settings:font_trebuchet'] = 'Trebuchet MS';
$string['settings:font_centurygothic'] = 'Century Gothic';
$string['settings:font_comicsans'] = 'Comic Sans MS';
$string['settings:font_opendyslexic'] = 'OpenDyslexic';
$string['settings:font_atkinson'] = 'Atkinson Hyperlegible';
$string['settings:font_lexend'] = 'Lexend';
$string['settings:ttsprovider'] = 'TTS provider';
$string['settings:ttsprovider_desc'] = 'Select which text-to-speech service to use.';
$string['settings:ttsprovider_google'] = 'Google Cloud Text-to-Speech';
$string['settings:ttsprovider_elevenlabs'] = 'ElevenLabs';
$string['settings:gcpcredentials'] = 'Google Cloud credentials JSON';
$string['settings:gcpcredentials_desc'] = 'Paste the service account JSON key contents used for Google Cloud Text-to-Speech.';
$string['settings:gcpvoice'] = 'Google Cloud default voice';
$string['settings:gcpvoice_desc'] = 'Voice name, e.g. en-US-Neural2-A.';
$string['settings:gcplanguage'] = 'Google Cloud language code';
$string['settings:gcplanguage_desc'] = 'BCP-47 language code, e.g. en-US.';
$string['settings:elevenlabskey'] = 'ElevenLabs API key';
$string['settings:elevenlabskey_desc'] = 'Paste the secret API key from ElevenLabs (it must start with sk_). Do not paste the key ID — that will be rejected by the API.';
$string['settings:elevenlabsvoice'] = 'ElevenLabs voice ID';
$string['settings:elevenlabsvoice_desc'] = 'Default voice identifier from ElevenLabs (Voices page → voice → ID).';
$string['settings:pdfengine'] = 'PDF engine';
$string['settings:pdfengine_desc'] = 'How HTML is converted to PDF.';
$string['settings:pdfengine_none'] = 'Not configured';
$string['settings:pdfengine_gotenberg'] = 'Gotenberg (HTTP)';
$string['settings:pdfengine_weasyprint'] = 'WeasyPrint (HTTP)';
$string['settings:pdfengine_libreoffice'] = 'LibreOffice (local binary)';
$string['settings:pdfengineurl'] = 'PDF engine URL';
$string['settings:pdfengineurl_desc'] = 'Base URL for Gotenberg or WeasyPrint, e.g. http://gotenberg:3000.';
$string['settings:libreofficepath'] = 'LibreOffice binary path';
$string['settings:libreofficepath_desc'] = 'Path to soffice binary, e.g. /usr/bin/soffice.';
$string['settings:maxcontentlength'] = 'Maximum content length (characters)';
$string['settings:maxcontentlength_desc'] = 'Reject conversion when extracted text exceeds this length.';
$string['settings:jobbatchlimit'] = 'Jobs per cron run';
$string['settings:jobbatchlimit_desc'] = 'Maximum number of queued jobs to process each minute.';
$string['settings:staledays'] = 'Purge stale files after (days)';
$string['settings:staledays_desc'] = 'Remove completed job files whose content hash no longer matches after this many days.';

$string['course:enable'] = 'Enable Learning Prism';
$string['course:enable_help'] = 'When enabled, students and teachers can request and download accessible alternate formats (DOCX, PDF, EPUB, audio) for Book, Page, Label, and Articulate Rise SCORM activities.';
$string['course:pregenerate'] = 'Pre-generate formats';
$string['course:report'] = 'Learning Prism report';

$string['privacy:metadata:local_learningprism_job'] = 'Stores format conversion jobs.';
$string['privacy:metadata:local_learningprism_job:userid'] = 'The user who created the job.';
$string['privacy:metadata:local_learningprism_job:cmid'] = 'The course module the job relates to.';
$string['privacy:metadata:local_learningprism_job:format'] = 'The requested format.';
$string['privacy:metadata:local_learningprism_job:timecreated'] = 'When the job was created.';
$string['privacy:metadata:local_learningprism_request'] = 'Stores requests and downloads of alternate formats.';
$string['privacy:metadata:local_learningprism_request:userid'] = 'The user who requested or downloaded.';
$string['privacy:metadata:local_learningprism_request:type'] = 'Whether the action was a request or download.';
$string['privacy:metadata:local_learningprism_request:timecreated'] = 'When the action occurred.';
$string['privacy:metadata:ttsprovider'] = 'Text content may be sent to an external TTS provider to generate audio.';
$string['privacy:metadata:pdfengine'] = 'Activity HTML may be sent to an external PDF engine (Gotenberg or WeasyPrint).';
$string['privacy:metadata:corefiles'] = 'Converted files (Word, PDF, EPUB, audio) are stored in Moodle file storage.';
$string['privacy:metadata:preference:printbackground'] = 'Preferred print background colour for personal Word, PDF, and EPUB files.';
$string['privacy:metadata:preference:font'] = 'Preferred font for personal Word, PDF, and EPUB files.';
$string['privacy:metadata:local_learningprism_job:personal'] = 'Whether the file is a private personalised copy.';

$string['icon:request'] = 'Request alternate formats';
$string['icon:processing'] = 'Formats are being generated';
$string['icon:download'] = 'Download alternate formats';
$string['icon:error'] = 'Format generation failed';

$string['modal:title'] = 'OCA Learning Prism';
$string['modal:request'] = 'Request';
$string['modal:requestorregenerate'] = 'Request or regenerate selected';
$string['modal:download'] = 'Download';
$string['modal:cancel'] = 'Cancel';
$string['modal:pregenerate'] = 'Pre-generate missing formats';
$string['modal:selectformats'] = 'Select formats. Tick a ready format to regenerate it.';
$string['modal:noneselected'] = 'Select at least one format.';
$string['modal:statusqueued'] = 'Queued — you will be notified when ready.';
$string['modal:statusprocessing'] = 'Processing — please wait.';
$string['modal:statusready'] = 'Ready to download.';
$string['modal:statuserror'] = 'Error: {$a}';
$string['modal:nofile'] = 'No file available yet.';
$string['modal:stale'] = 'Content has changed. Request formats again.';
$string['modal:yourversion'] = 'Your version:';
$string['modal:downloadmine'] = 'Download my version';
$string['modal:myoptions'] = 'My print options';
$string['modal:myoptions_help'] = 'Background colour and font apply only to a private copy of Word, PDF, and EPUB. Other people still see the default files. Audio is not personalised.';
$string['modal:requestmine'] = 'Request my version of selected';

$string['format:docx'] = 'Word document (DOCX)';
$string['format:pdf'] = 'PDF';
$string['format:epub'] = 'Ebook (EPUB)';
$string['format:audio'] = 'Audio (TTS)';

$string['message:success:subject'] = 'Alternate format ready';
$string['message:success:body'] = 'Your {$a->format} version of "{$a->name}" is ready to download.';
$string['message:failure:subject'] = 'Alternate format failed';
$string['message:failure:body'] = 'Generation of {$a->format} for "{$a->name}" failed: {$a->error}';

$string['error:notenabled'] = 'Learning Prism is not enabled for this course.';
$string['error:plugindisabled'] = 'Learning Prism is disabled site-wide.';
$string['error:unsupportedmodule'] = 'This activity type is not supported.';
$string['error:scormnotrise'] = 'This SCORM package is not an Articulate Rise export, so alternate formats cannot be generated.';
$string['error:contenttoolong'] = 'Activity content exceeds the maximum allowed length.';
$string['error:pdfnotconfigured'] = 'PDF conversion is not configured.';
$string['error:ttsnotconfigured'] = 'Text-to-speech is not configured.';
$string['error:conversionfailed'] = 'Conversion failed.';
$string['error:nopermission'] = 'You do not have permission to perform this action.';

$string['task:process_jobs'] = 'Process Learning Prism conversion jobs';
$string['task:purge_stale_files'] = 'Purge stale Learning Prism files';

$string['report:heading'] = 'Learning Prism jobs';
$string['report:activity'] = 'Activity';
$string['report:format'] = 'Format';
$string['report:status'] = 'Status';
$string['report:error'] = 'Details';
$string['report:timemodified'] = 'Last updated';
$string['report:actions'] = 'Actions';
$string['report:reprocess'] = 'Regenerate';
$string['report:requeued'] = 'Job queued for regeneration.';
$string['report:empty'] = 'No jobs found for this course.';

$string['status:queued'] = 'Queued';
$string['status:processing'] = 'Processing';
$string['status:ready'] = 'Ready';
$string['status:error'] = 'Error';

$string['cachedef_courseenabled'] = 'Course Learning Prism enabled flag';
