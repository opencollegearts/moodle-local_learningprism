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

    return true;
}
