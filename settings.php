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
 * Admin settings for local_learningprism.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_learningprism', get_string('pluginname', 'local_learningprism'));

    $settings->add(new admin_setting_heading(
        'local_learningprism/heading',
        get_string('settings:heading', 'local_learningprism'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_learningprism/enable',
        get_string('settings:enable', 'local_learningprism'),
        get_string('settings:enable_desc', 'local_learningprism'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_learningprism/enabledocx',
        get_string('settings:enabledocx', 'local_learningprism'),
        get_string('settings:enabledocx_desc', 'local_learningprism'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_learningprism/enablepdf',
        get_string('settings:enablepdf', 'local_learningprism'),
        get_string('settings:enablepdf_desc', 'local_learningprism'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_learningprism/enableepub',
        get_string('settings:enableepub', 'local_learningprism'),
        get_string('settings:enableepub_desc', 'local_learningprism'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_learningprism/enableaudio',
        get_string('settings:enableaudio', 'local_learningprism'),
        get_string('settings:enableaudio_desc', 'local_learningprism'),
        1
    ));

    $settings->add(new admin_setting_heading(
        'local_learningprism/ttsheading',
        get_string('settings:ttsheading', 'local_learningprism'),
        get_string('settings:ttsheading_desc', 'local_learningprism')
    ));

    $bgoptions = [];
    foreach (\local_learningprism\html_normalizer::print_background_presets() as $key => $hex) {
        $bgoptions[$key] = get_string('settings:printbackground_' . $key, 'local_learningprism');
    }
    $settings->add(new admin_setting_configselect(
        'local_learningprism/printbackground',
        get_string('settings:printbackground', 'local_learningprism'),
        get_string('settings:printbackground_desc', 'local_learningprism'),
        'white',
        $bgoptions
    ));

    $settings->add(new admin_setting_configselect(
        'local_learningprism/ttsprovider',
        get_string('settings:ttsprovider', 'local_learningprism'),
        get_string('settings:ttsprovider_desc', 'local_learningprism'),
        'google',
        [
            'google' => get_string('settings:ttsprovider_google', 'local_learningprism'),
            'elevenlabs' => get_string('settings:ttsprovider_elevenlabs', 'local_learningprism'),
        ]
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_learningprism/gcpcredentials',
        get_string('settings:gcpcredentials', 'local_learningprism'),
        get_string('settings:gcpcredentials_desc', 'local_learningprism'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/gcpvoice',
        get_string('settings:gcpvoice', 'local_learningprism'),
        get_string('settings:gcpvoice_desc', 'local_learningprism'),
        'en-US-Neural2-A',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/gcplanguage',
        get_string('settings:gcplanguage', 'local_learningprism'),
        get_string('settings:gcplanguage_desc', 'local_learningprism'),
        'en-US',
        PARAM_TEXT
    ));

    // Plain text field: passwordunmask uses href="#" edit controls that jump to top of page
    // when the value is empty / JS init fails on long admin pages (esp. Snap/Open LMS).
    $settings->add(new admin_setting_configtext(
        'local_learningprism/elevenlabskey',
        get_string('settings:elevenlabskey', 'local_learningprism'),
        get_string('settings:elevenlabskey_desc', 'local_learningprism'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/elevenlabsvoice',
        get_string('settings:elevenlabsvoice', 'local_learningprism'),
        get_string('settings:elevenlabsvoice_desc', 'local_learningprism'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_learningprism/pdfheading',
        get_string('settings:pdfheading', 'local_learningprism'),
        get_string('settings:pdfheading_desc', 'local_learningprism')
    ));

    $settings->add(new admin_setting_configselect(
        'local_learningprism/pdfengine',
        get_string('settings:pdfengine', 'local_learningprism'),
        get_string('settings:pdfengine_desc', 'local_learningprism'),
        'none',
        [
            'none' => get_string('settings:pdfengine_none', 'local_learningprism'),
            'gotenberg' => get_string('settings:pdfengine_gotenberg', 'local_learningprism'),
            'weasyprint' => get_string('settings:pdfengine_weasyprint', 'local_learningprism'),
            'libreoffice' => get_string('settings:pdfengine_libreoffice', 'local_learningprism'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/pdfengineurl',
        get_string('settings:pdfengineurl', 'local_learningprism'),
        get_string('settings:pdfengineurl_desc', 'local_learningprism'),
        'http://localhost:3000',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/libreofficepath',
        get_string('settings:libreofficepath', 'local_learningprism'),
        get_string('settings:libreofficepath_desc', 'local_learningprism'),
        '/usr/bin/soffice',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/maxcontentlength',
        get_string('settings:maxcontentlength', 'local_learningprism'),
        get_string('settings:maxcontentlength_desc', 'local_learningprism'),
        200000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/jobbatchlimit',
        get_string('settings:jobbatchlimit', 'local_learningprism'),
        get_string('settings:jobbatchlimit_desc', 'local_learningprism'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/googleuploadsperrun',
        get_string('settings:googleuploadsperrun', 'local_learningprism'),
        get_string('settings:googleuploadsperrun_desc', 'local_learningprism'),
        1,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/staledays',
        get_string('settings:staledays', 'local_learningprism'),
        get_string('settings:staledays_desc', 'local_learningprism'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_learningprism/notebookheading',
        get_string('settings:notebookheading', 'local_learningprism'),
        get_string('settings:notebookheading_desc', 'local_learningprism')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_learningprism/enablenotebook',
        get_string('settings:enablenotebook', 'local_learningprism'),
        get_string('settings:enablenotebook_desc', 'local_learningprism'),
        0
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_learningprism/nbcredentials',
        get_string('settings:nbcredentials', 'local_learningprism'),
        get_string('settings:nbcredentials_desc', 'local_learningprism'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/nbprojectnumber',
        get_string('settings:nbprojectnumber', 'local_learningprism'),
        get_string('settings:nbprojectnumber_desc', 'local_learningprism'),
        '',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configselect(
        'local_learningprism/nblocation',
        get_string('settings:nblocation', 'local_learningprism'),
        get_string('settings:nblocation_desc', 'local_learningprism'),
        'global',
        [
            'global' => get_string('settings:nblocation_global', 'local_learningprism'),
            'us' => get_string('settings:nblocation_us', 'local_learningprism'),
            'eu' => get_string('settings:nblocation_eu', 'local_learningprism'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'local_learningprism/nbidentity',
        get_string('settings:nbidentity', 'local_learningprism'),
        get_string('settings:nbidentity_desc', 'local_learningprism'),
        'google',
        [
            'google' => get_string('settings:nbidentity_google', 'local_learningprism'),
            'thirdparty' => get_string('settings:nbidentity_thirdparty', 'local_learningprism'),
        ]
    ));

    $settings->add(new admin_setting_configselect(
        'local_learningprism/nbsourceformat',
        get_string('settings:nbsourceformat', 'local_learningprism'),
        get_string('settings:nbsourceformat_desc', 'local_learningprism'),
        'docx',
        [
            'docx' => get_string('settings:nbsourceformat_docx', 'local_learningprism'),
            'pdf' => get_string('settings:nbsourceformat_pdf', 'local_learningprism'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/nbmaxbytes',
        get_string('settings:nbmaxbytes', 'local_learningprism'),
        get_string('settings:nbmaxbytes_desc', 'local_learningprism'),
        524288000,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_learningprism/archiveheading',
        get_string('settings:archiveheading', 'local_learningprism'),
        get_string('settings:archiveheading_desc', 'local_learningprism')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_learningprism/enablearchive',
        get_string('settings:enablearchive', 'local_learningprism'),
        get_string('settings:enablearchive_desc', 'local_learningprism'),
        0
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_learningprism/archivecredentials',
        get_string('settings:archivecredentials', 'local_learningprism'),
        get_string('settings:archivecredentials_desc', 'local_learningprism'),
        '',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/archivedelegateduser',
        get_string('settings:archivedelegateduser', 'local_learningprism'),
        get_string('settings:archivedelegateduser_desc', 'local_learningprism'),
        'extranet@oca.ac.uk',
        PARAM_EMAIL
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/archiveparentfolder',
        get_string('settings:archiveparentfolder', 'local_learningprism'),
        get_string('settings:archiveparentfolder_desc', 'local_learningprism'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_learningprism/archivemaxbytes',
        get_string('settings:archivemaxbytes', 'local_learningprism'),
        get_string('settings:archivemaxbytes_desc', 'local_learningprism'),
        524288000,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
