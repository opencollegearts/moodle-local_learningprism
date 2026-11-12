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
 * Upgrade steps for local_learningprism.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_learningprism_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2026102210) {
        if (get_config('local_learningprism', 'enableepub') === false) {
            set_config('enableepub', 1, 'local_learningprism');
        }
        if (get_config('local_learningprism', 'printbackground') === false) {
            set_config('printbackground', 'white', 'local_learningprism');
        }
        upgrade_plugin_savepoint(true, 2026102210, 'local', 'learningprism');
    }

    if ($oldversion < 2026102211) {
        upgrade_plugin_savepoint(true, 2026102211, 'local', 'learningprism');
    }

    if ($oldversion < 2026102212) {
        upgrade_plugin_savepoint(true, 2026102212, 'local', 'learningprism');
    }

    if ($oldversion < 2026102213) {
        upgrade_plugin_savepoint(true, 2026102213, 'local', 'learningprism');
    }

    if ($oldversion < 2026102214) {
        $table = new xmldb_table('local_learningprism_job');
        $field = new xmldb_field('personal', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'userid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('optionshash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null, 'personal');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new xmldb_index(
            'personalidx',
            XMLDB_INDEX_NOTUNIQUE,
            ['cmid', 'contenthash', 'format', 'personal', 'userid', 'optionshash']
        );
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026102214, 'local', 'learningprism');
    }

    if ($oldversion < 2026102215) {
        upgrade_plugin_savepoint(true, 2026102215, 'local', 'learningprism');
    }

    if ($oldversion < 2026102216) {
        upgrade_plugin_savepoint(true, 2026102216, 'local', 'learningprism');
    }

    if ($oldversion < 2026102217) {
        upgrade_plugin_savepoint(true, 2026102217, 'local', 'learningprism');
    }

    if ($oldversion < 2026102218) {
        upgrade_plugin_savepoint(true, 2026102218, 'local', 'learningprism');
    }

    if ($oldversion < 2026102219) {
        upgrade_plugin_savepoint(true, 2026102219, 'local', 'learningprism');
    }

    if ($oldversion < 2026102220) {
        upgrade_plugin_savepoint(true, 2026102220, 'local', 'learningprism');
    }

    if ($oldversion < 2026102600) {
        upgrade_plugin_savepoint(true, 2026102600, 'local', 'learningprism');
    }

    if ($oldversion < 2026102601) {
        upgrade_plugin_savepoint(true, 2026102601, 'local', 'learningprism');
    }

    if ($oldversion < 2026102602) {
        upgrade_plugin_savepoint(true, 2026102602, 'local', 'learningprism');
    }

    if ($oldversion < 2026102603) {
        upgrade_plugin_savepoint(true, 2026102603, 'local', 'learningprism');
    }

    if ($oldversion < 2026102604) {
        upgrade_plugin_savepoint(true, 2026102604, 'local', 'learningprism');
    }

    if ($oldversion < 2026102605) {
        upgrade_plugin_savepoint(true, 2026102605, 'local', 'learningprism');
    }

    if ($oldversion < 2026102700) {
        upgrade_plugin_savepoint(true, 2026102700, 'local', 'learningprism');
    }

    if ($oldversion < 2026102712) {
        $table = new xmldb_table('local_learningprism_notebook');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('notebookid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('url', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sharemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'group');
        $table->add_field('groupemail', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued');
        $table->add_field('errorinfo', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('skipinfo', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseiduk', XMLDB_KEY_UNIQUE, ['courseid']);
        $table->add_index('statusidx', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_learningprism_nbsource');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('notebookid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourcetype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('gsourceid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued');
        $table->add_field('errorinfo', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_notebookid', XMLDB_KEY_FOREIGN, ['notebookid'], 'local_learningprism_notebook', ['id']);
        $table->add_index('sourcekeyidx', XMLDB_INDEX_UNIQUE, ['notebookid', 'sourcetype', 'cmid', 'fileid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026102712, 'local', 'learningprism');
    }

    if ($oldversion < 2026102713) {
        upgrade_plugin_savepoint(true, 2026102713, 'local', 'learningprism');
    }

    if ($oldversion < 2026102714) {
        upgrade_plugin_savepoint(true, 2026102714, 'local', 'learningprism');
    }

    if ($oldversion < 2026110300) {
        upgrade_plugin_savepoint(true, 2026110300, 'local', 'learningprism');
    }

    if ($oldversion < 2026110301) {
        $table = new xmldb_table('local_learningprism_archive');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('folderid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('url', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('sharemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'group');
        $table->add_field('groupemail', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued');
        $table->add_field('errorinfo', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('skipinfo', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('courseiduk', XMLDB_KEY_UNIQUE, ['courseid']);
        $table->add_index('statusidx', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_learningprism_archiveitem');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('archiveid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sectionnum', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('itemindex', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcetype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('fileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('contenthash', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, null);
        $table->add_field('drivefileid', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('displayname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sectionfolder', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'queued');
        $table->add_field('errorinfo', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_archiveid', XMLDB_KEY_FOREIGN, ['archiveid'], 'local_learningprism_archive', ['id']);
        $table->add_index('itemkeyidx', XMLDB_INDEX_UNIQUE, ['archiveid', 'sourcetype', 'sectionnum', 'cmid', 'fileid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026110301, 'local', 'learningprism');
    }

    if ($oldversion < 2026110302) {
        $table = new xmldb_table('local_learningprism_archive');
        $field = new xmldb_field('sharemode', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'group', 'url');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        // CHAR NOTNULL must not use '' as DEFAULT (XMLDB debugging / Whoops).
        $field = new xmldb_field('groupemail', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'sharemode');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026110302, 'local', 'learningprism');
    }

    if ($oldversion < 2026110303) {
        upgrade_plugin_savepoint(true, 2026110303, 'local', 'learningprism');
    }

    if ($oldversion < 2026110304) {
        upgrade_plugin_savepoint(true, 2026110304, 'local', 'learningprism');
    }

    if ($oldversion < 2026111211) {
        // Course audio / voice settings.
        $table = new xmldb_table('local_learningprism_course');
        $field = new xmldb_field('allowstudentaudio', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'enabled');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('defaultvoice', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null, 'allowstudentaudio');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {local_learningprism_course} SET defaultvoice = '' WHERE defaultvoice IS NULL");
        }

        // Archive item format + format folder.
        $table = new xmldb_table('local_learningprism_archiveitem');
        $field = new xmldb_field('format', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'docx', 'fileid');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('formatfolder', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, null, 'sectionfolder');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
            $DB->execute("UPDATE {local_learningprism_archiveitem} SET formatfolder = 'DocX' WHERE formatfolder IS NULL OR formatfolder = ''");
        }
        $oldindex = new xmldb_index('itemkeyidx', XMLDB_INDEX_UNIQUE, ['archiveid', 'sourcetype', 'sectionnum', 'cmid', 'fileid']);
        if ($dbman->table_exists($table) && $dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $newindex = new xmldb_index('itemkeyidx', XMLDB_INDEX_UNIQUE, [
            'archiveid', 'sourcetype', 'sectionnum', 'cmid', 'fileid', 'format',
        ]);
        if ($dbman->table_exists($table) && !$dbman->index_exists($table, $newindex)) {
            $dbman->add_index($table, $newindex);
        }

        // Migrate legacy single-voice settings into catalogs.
        $gcpvoice = trim((string) get_config('local_learningprism', 'gcpvoice'));
        if ($gcpvoice === '') {
            $gcpvoice = 'en-US-Neural2-A';
        }
        if (get_config('local_learningprism', 'gcpvoices') === false || get_config('local_learningprism', 'gcpvoices') === '') {
            set_config('gcpvoices', $gcpvoice . '|' . $gcpvoice, 'local_learningprism');
        }
        if (get_config('local_learningprism', 'gcpvoicedefault') === false || get_config('local_learningprism', 'gcpvoicedefault') === '') {
            set_config('gcpvoicedefault', $gcpvoice, 'local_learningprism');
        }
        $elvoice = trim((string) get_config('local_learningprism', 'elevenlabsvoice'));
        if ($elvoice !== '' && (get_config('local_learningprism', 'elevenlabsvoices') === false
                || get_config('local_learningprism', 'elevenlabsvoices') === '')) {
            set_config('elevenlabsvoices', $elvoice . '|' . $elvoice, 'local_learningprism');
            set_config('elevenlabsvoicedefault', $elvoice, 'local_learningprism');
        }
        if (get_config('local_learningprism', 'archiveformats') === false) {
            set_config('archiveformats', 'docx', 'local_learningprism');
        }

        upgrade_plugin_savepoint(true, 2026111211, 'local', 'learningprism');
    }

    return true;
}
