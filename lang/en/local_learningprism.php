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
$string['learningprism:viewnotebook'] = 'Open the course Google Notebook';
$string['learningprism:viewarchive'] = 'Open the course Google Drive archive';

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
$string['settings:enableaudio_desc'] = 'Allow generation of text-to-speech audio. Optional — Word and EPUB work without it. If you enable audio, use credentials from your own Google Cloud or ElevenLabs account. These are not a plugin licence and no extra purchase is required through Moodle Marketplace.';
$string['settings:ttsheading'] = 'Text-to-speech (optional)';
$string['settings:ttsheading_desc'] = 'Audio uses your site\'s own Google Cloud or ElevenLabs account. Leave these fields empty if you do not need audio. They are not a Learning Prism licence key and the plugin does not require a subscription or off-Marketplace payment.';
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
$string['settings:ttsprovider_desc'] = 'Which third-party TTS service to use when audio is enabled. You supply your own account; this is not a plugin licence.';
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
$string['settings:pdfheading'] = 'PDF conversion (optional)';
$string['settings:pdfheading_desc'] = 'PDF needs a self-hosted engine such as Gotenberg, WeasyPrint, or LibreOffice. Word and EPUB do not. This is not a paid plugin add-on.';
$string['settings:pdfengine'] = 'PDF engine';
$string['settings:pdfengine_desc'] = 'How HTML is converted to PDF. Self-hosted only; not a Marketplace paid extra.';
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
$string['settings:notebookheading'] = 'Google Notebook (optional)';
$string['settings:notebookheading_desc'] = 'Create a Gemini Notebook Enterprise notebook per course and upload converted activities plus PDF, DOCX, MP3, and MP4 files. This is a Google Cloud Pre-GA API. Leave disabled unless your organisation has Gemini Notebook Enterprise (NotebookLM Enterprise) seats.';
$string['settings:enablenotebook'] = 'Enable Google Notebook';
$string['settings:enablenotebook_desc'] = 'Allow teachers to generate a shared course notebook. Requires a Google Cloud service account, project number, and Gemini Notebook Enterprise setup. <strong>Enterprise Gemini / NotebookLM Enterprise licences are required for this feature</strong> — including for every user a notebook is shared with (viewers as well as editors). Google Workspace Education Plus and consumer NotebookLM do not grant access.';
$string['settings:nbcredentials'] = 'Notebook service account JSON';
$string['settings:nbcredentials_desc'] = 'Optional. Paste a dedicated service account JSON key for NotebookLM. If empty, Learning Prism reuses the Google Cloud Text-to-Speech JSON above. That account must have Cloud NotebookLM Admin (`roles/discoveryengine.notebookLmOwner`) on the project, in the same location as **Notebook location**. A TTS-only account will be denied `discoveryengine.notebooks.create`.';
$string['settings:nbprojectnumber'] = 'Google Cloud project number';
$string['settings:nbprojectnumber_desc'] = 'Numeric project number (not the project ID string). Used in API paths and the notebook URL.';
$string['settings:nblocation'] = 'Notebook location';
$string['settings:nblocation_desc'] = 'Gemini Notebook Enterprise multi-region. Licences are per location.';
$string['settings:nblocation_global'] = 'Global';
$string['settings:nblocation_us'] = 'United States';
$string['settings:nblocation_eu'] = 'European Union';
$string['settings:nbidentity'] = 'Notebook identity host';
$string['settings:nbidentity_desc'] = 'URL host for opening notebooks in the browser. Use the third-party host if users sign in through your IdP rather than a Google identity.';
$string['settings:nbidentity_google'] = 'Google identity (notebook.cloud.google.com)';
$string['settings:nbidentity_thirdparty'] = 'Third-party identity (notebook.cloud.google)';
$string['settings:nbsourceformat'] = 'Converted activity format';
$string['settings:nbsourceformat_desc'] = 'Book, Page, Label, and Rise SCORM are converted before upload. DOCX is generated in PHP. PDF is used only when a PDF engine is configured; otherwise DOCX is used.';
$string['settings:nbsourceformat_docx'] = 'Word (DOCX)';
$string['settings:nbsourceformat_pdf'] = 'PDF (when a PDF engine is configured)';
$string['settings:nbmaxbytes'] = 'Maximum source file size (bytes)';
$string['settings:nbmaxbytes_desc'] = 'Skip native course files larger than this. Capped at 500 MB (Google’s source size limit).';
$string['settings:archiveheading'] = 'Google Drive archive (optional)';
$string['settings:archiveheading_desc'] = 'Create an indexed course folder tree in Google Drive (section folders and numbered DOCX/PDF files). Uses a service account with Workspace domain-wide delegation.';
$string['settings:enablearchive'] = 'Enable Google Drive archive';
$string['settings:enablearchive_desc'] = 'Allow teachers to generate a Drive archive of the course. Requires a service account JSON and Google Workspace <strong>domain-wide delegation</strong> so the account can impersonate the delegated user and write to their My Drive. Refresh deletes and recreates the course folder. For “Google Group (create if missing)”, authorise Admin SDK Groups and Group Member scopes so the plugin can create the group and sync enrolled users into it.';
$string['settings:archivecredentials'] = 'Archive service account JSON';
$string['settings:archivecredentials_desc'] = 'Optional. Paste a dedicated service account JSON key for Drive. If empty, Learning Prism reuses the Google Cloud Text-to-Speech JSON above. Authorise domain-wide delegation for Drive (`https://www.googleapis.com/auth/drive`) and, for group create/membership sync/lockdown: `https://www.googleapis.com/auth/admin.directory.group`, `https://www.googleapis.com/auth/admin.directory.group.member`, and `https://www.googleapis.com/auth/apps.groups.settings`.';
$string['settings:archivedelegateduser'] = 'Delegated Workspace user';
$string['settings:archivedelegateduser_desc'] = 'Email of the Workspace user whose My Drive will own the archives (domain-wide delegation subject). Default: extranet@oca.ac.uk. For creating Google Groups and managing membership/settings, this user needs permission to create groups and manage their access settings in Workspace.';
$string['settings:archiveparentfolder'] = 'Parent Drive folder ID (optional)';
$string['settings:archiveparentfolder_desc'] = 'If set, course archive folders are created under this folder in the delegated user’s Drive. Leave empty to use My Drive root.';
$string['settings:archivemaxbytes'] = 'Maximum archive file size (bytes)';
$string['settings:archivemaxbytes_desc'] = 'Skip native PDF/DOCX course files larger than this. Capped at 500 MB.';

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
$string['privacy:metadata:local_learningprism_notebook'] = 'Stores the course Google Notebook and who generated it.';
$string['privacy:metadata:local_learningprism_notebook:userid'] = 'The user who queued the notebook generate or share action.';
$string['privacy:metadata:local_learningprism_notebook:courseid'] = 'The course the notebook belongs to.';
$string['privacy:metadata:local_learningprism_notebook:sharemode'] = 'Whether the notebook is shared with a Google Group or course participants.';
$string['privacy:metadata:local_learningprism_notebook:groupemail'] = 'Google Group email used when sharing the notebook.';
$string['privacy:metadata:local_learningprism_notebook:timecreated'] = 'When the notebook record was created.';
$string['privacy:metadata:notebooklm'] = 'Course files and, in participants share mode, user email addresses are sent to Gemini Notebook Enterprise.';
$string['privacy:metadata:notebooklm:files'] = 'Converted activity files and course PDF, DOCX, MP3, or MP4 resources uploaded as notebook sources.';
$string['privacy:metadata:notebooklm:email'] = 'Email addresses used to share the notebook (Google Group or enrolled users).';
$string['privacy:metadata:local_learningprism_archive'] = 'Stores the course Google Drive archive and who generated it.';
$string['privacy:metadata:local_learningprism_archive:userid'] = 'The user who queued the archive generate action.';
$string['privacy:metadata:local_learningprism_archive:courseid'] = 'The course the archive belongs to.';
$string['privacy:metadata:local_learningprism_archive:sharemode'] = 'How the Drive folder is shared (Google Group, participants, or anyone with the link).';
$string['privacy:metadata:local_learningprism_archive:groupemail'] = 'Google Group email used when sharing the Drive archive.';
$string['privacy:metadata:local_learningprism_archive:timecreated'] = 'When the archive record was created.';
$string['privacy:metadata:googledrive'] = 'Course documents are uploaded to Google Drive under the delegated Workspace user, and may be shared with a Google Group, enrolled user emails, or anyone with the link.';
$string['privacy:metadata:googledrive:files'] = 'Converted and native course documents uploaded into the indexed Drive archive.';
$string['privacy:metadata:googledrive:email'] = 'Email addresses used when sharing the archive folder (Google Group or enrolled users).';

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
$string['error:notebooknotconfigured'] = 'Google Notebook is not configured.';
$string['error:notebooknotcreated'] = 'Generate the notebook before updating sharing.';
$string['error:notebookapi'] = 'The Google Notebook request failed. Check the scheduled task logs and your Google Cloud settings.';
$string['error:notebooknoshare'] = 'No valid email addresses were available to share the notebook.';
$string['error:notebookupload'] = 'The file could not be uploaded to Google Notebook.';
$string['error:archivenotconfigured'] = 'Google Drive archive is not configured.';
$string['error:archivenotcreated'] = 'Generate the archive before updating sharing.';
$string['error:archiveapi'] = 'The Google Drive archive request failed. Check the scheduled task logs, domain-wide delegation, and your Google Cloud settings.';
$string['error:archiveupload'] = 'The file could not be uploaded to Google Drive.';
$string['error:archivenoshare'] = 'No valid email addresses were available to share the archive.';
$string['error:archivegroupemail'] = 'Enter a valid Google Group email address for this sharing mode.';
$string['error:archivegroupcreate'] = 'The Google Group could not be created. Check domain-wide delegation for the Admin SDK Groups scope and that the delegated user can create groups.';
$string['error:archivegroupsettings'] = 'The Google Group was created but its access settings could not be locked down. Authorise the Groups Settings scope (`https://www.googleapis.com/auth/apps.groups.settings`) in domain-wide delegation and enable the Groups Settings API.';

$string['task:process_jobs'] = 'Process Learning Prism conversion jobs';
$string['task:purge_stale_files'] = 'Purge stale Learning Prism files';
$string['task:sync_notebooks'] = 'Sync Learning Prism Google Notebooks';
$string['task:sync_archives'] = 'Sync Learning Prism Google Drive archives';

$string['archive:heading'] = 'Google Drive archive';
$string['archive:open'] = 'Open Drive archive';
$string['archive:intro'] = 'Generate an indexed archive of this course in Google Drive. Each section becomes a numbered folder. Section descriptions, Assignments, Forums, Books, Pages, Labels, Rise SCORM, and PDF/DOCX files become numbered documents. Audio and video are skipped. Generate or refresh replaces the previous course folder, then applies the sharing option you choose.';
$string['archive:generate'] = 'Generate or refresh archive';
$string['archive:updatesharing'] = 'Update sharing';
$string['archive:queued'] = 'Drive archive queued. Run the Google Drive archives scheduled task (or wait for cron).';
$string['archive:sharequeued'] = 'Archive sharing queued. Run the Google Drive archives scheduled task (or wait for cron).';
$string['archive:currentstatus'] = 'Status';
$string['archive:skipped'] = 'Skipped items';
$string['archive:skip:size'] = 'File exceeds the maximum archive size.';
$string['archive:sectionoverview'] = 'Section overview';
$string['archive:sharemode'] = 'Share with';
$string['archive:sharemode_help'] = 'Viewer access on the Drive folder. Prefer a Google Group when you have one. “Create if missing” also syncs enrolled Moodle users into that group’s membership (Update sharing re-syncs). Participant mode shares with each enrolled email instead. Anyone with the link makes the folder link-readable.';
$string['archive:sharemode_group'] = 'Existing Google Group';
$string['archive:sharemode_groupcreate'] = 'Google Group (create if missing)';
$string['archive:sharemode_participants'] = 'Course participants';
$string['archive:sharemode_anyone'] = 'Anyone with the link can view';
$string['archive:groupemail'] = 'Google Group email';
$string['archive:groupemail_invalid'] = 'Enter a valid Google Group email address.';
$string['archive:groupdescription'] = 'Learning Prism archive group for {$a}';
$string['archive:participantsnotice'] = 'Each participant’s Moodle email must match their Google Workspace identity. Sharing is refreshed automatically when users are enrolled or unenrolled.';
$string['archive:groupcreatenotice'] = 'If the group does not exist it will be created with member-only posting and membership visibility (not organisation-wide). Enrolled course users are added as members. Update sharing re-applies those settings and re-syncs membership. Moodle emails must match Workspace identities.';
$string['archive:anyonenotice'] = 'The folder will be readable by anyone who has the link. Your Google Workspace policies may still block “anyone with the link” sharing.';
$string['archive:status:queued'] = 'Queued';
$string['archive:status:converting'] = 'Converting documents';
$string['archive:status:uploading'] = 'Uploading to Drive';
$string['archive:status:sharing'] = 'Sharing';
$string['archive:status:ready'] = 'Ready';
$string['archive:status:error'] = 'Error';

$string['notebook:heading'] = 'Google Notebook';
$string['notebook:open'] = 'Open Google Notebook';
$string['notebook:manage'] = 'Notebook settings';
$string['notebook:intro'] = 'Generate a Gemini Notebook Enterprise notebook for this course. Learning Prism converts Book, Page, Label, and Rise SCORM activities to Word (or PDF if configured), then uploads those files plus any File or Folder resources that are PDF, DOCX, MP3, or MP4. Conversions and uploads run on the scheduled tasks. Students can open the notebook only if they have a NotebookLM Enterprise licence, the Cloud NotebookLM User role, and access via the Google Group or a matching Workspace email.';
$string['notebook:sharemode'] = 'Share with';
$string['notebook:sharemode_help'] = 'Prefer a Google Group that already contains the class. Course participants uses each Moodle user\'s email address, which must match their Google Workspace / IdP identity.';
$string['notebook:sharemode_group'] = 'Google Group (recommended)';
$string['notebook:sharemode_participants'] = 'Course participants';
$string['notebook:groupemail'] = 'Google Group email';
$string['notebook:groupemail_invalid'] = 'Enter a valid Google Group email address.';
$string['notebook:participantsnotice'] = 'Each participant\'s Moodle email address must match their Google Workspace (or IdP) email. Users without a matching identity cannot open the notebook even if the URL is shown.';
$string['notebook:generate'] = 'Generate or refresh notebook';
$string['notebook:updatesharing'] = 'Update sharing';
$string['notebook:queued'] = 'Notebook sync queued. Run the Google Notebook scheduled task (or wait for cron).';
$string['notebook:sharequeued'] = 'Notebook sharing queued. Run the Google Notebook scheduled task (or wait for cron).';
$string['notebook:currentstatus'] = 'Status';
$string['notebook:skipped'] = 'Skipped sources';
$string['notebook:skip:size'] = 'File exceeds the maximum source size.';
$string['notebook:skip:limit'] = 'Notebook source limit (300) reached.';
$string['notebook:status:queued'] = 'Queued';
$string['notebook:status:converting'] = 'Converting activities';
$string['notebook:status:uploading'] = 'Uploading sources';
$string['notebook:status:sharing'] = 'Sharing';
$string['notebook:status:ready'] = 'Ready';
$string['notebook:status:error'] = 'Error';

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
