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
 * Post-install defaults and migration from local_multipleformats.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Install hook.
 */
function xmldb_local_learningprism_install() {
    global $DB;

    set_config('enable', 1, 'local_learningprism');
    set_config('enabledocx', 1, 'local_learningprism');
    set_config('enablepdf', 1, 'local_learningprism');
    set_config('enableepub', 1, 'local_learningprism');
    set_config('enableaudio', 1, 'local_learningprism');
    set_config('printbackground', 'white', 'local_learningprism');
    set_config('ttsprovider', 'google', 'local_learningprism');
    set_config('pdfengine', 'none', 'local_learningprism');
    set_config('jobbatchlimit', 5, 'local_learningprism');
    set_config('maxcontentlength', 200000, 'local_learningprism');
    set_config('staledays', 30, 'local_learningprism');

    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('local_multipleformats_course')) {
        return;
    }

    xmldb_local_learningprism_copy_table('local_multipleformats_course', 'local_learningprism_course');
    xmldb_local_learningprism_copy_table('local_multipleformats_job', 'local_learningprism_job');
    xmldb_local_learningprism_copy_table('local_multipleformats_request', 'local_learningprism_request');

    $oldconfigs = get_config('local_multipleformats');
    if ($oldconfigs) {
        foreach ($oldconfigs as $name => $value) {
            if ($name === 'version') {
                continue;
            }
            set_config($name, $value, 'local_learningprism');
        }
    }

    $DB->set_field('files', 'component', 'local_learningprism', ['component' => 'local_multipleformats']);

    $prefs = $DB->get_recordset_select('user_preferences', "name LIKE 'local_multipleformats_%'");
    foreach ($prefs as $pref) {
        $pref->name = str_replace('local_multipleformats_', 'local_learningprism_', $pref->name);
        $DB->update_record('user_preferences', $pref);
    }
    $prefs->close();

    $existingcaps = $DB->get_records_select('role_capabilities', "capability LIKE 'local/learningprism:%'");
    $capindex = [];
    foreach ($existingcaps as $existing) {
        $capindex[$existing->roleid . ':' . $existing->capability] = $existing;
    }
    $oldcaps = $DB->get_recordset_select('role_capabilities', "capability LIKE 'local/multipleformats:%'");
    foreach ($oldcaps as $rc) {
        $newcap = str_replace('local/multipleformats:', 'local/learningprism:', $rc->capability);
        $key = $rc->roleid . ':' . $newcap;
        if (isset($capindex[$key])) {
            $capindex[$key]->permission = $rc->permission;
            $DB->update_record('role_capabilities', $capindex[$key]);
        } else {
            $rc->capability = $newcap;
            $DB->update_record('role_capabilities', $rc);
        }
    }
    $oldcaps->close();
}

/**
 * Copy all rows from an old plugin table into the renamed table, keeping ids.
 *
 * @param string $oldname
 * @param string $newname
 */
function xmldb_local_learningprism_copy_table(string $oldname, string $newname): void {
    global $DB;

    if ($DB->count_records($newname) > 0) {
        return;
    }
    $rows = $DB->get_recordset($oldname);
    foreach ($rows as $row) {
        $DB->import_record($newname, $row);
    }
    $rows->close();
}
