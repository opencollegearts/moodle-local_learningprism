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

namespace local_learningprism\form;

use local_learningprism\local\archive\sync_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Course Google Drive archive generate / share form.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class archive_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'sharemode', get_string('archive:sharemode', 'local_learningprism'), [
            sync_manager::SHARE_GROUP => get_string('archive:sharemode_group', 'local_learningprism'),
            sync_manager::SHARE_GROUP_CREATE => get_string('archive:sharemode_groupcreate', 'local_learningprism'),
            sync_manager::SHARE_PARTICIPANTS => get_string('archive:sharemode_participants', 'local_learningprism'),
            sync_manager::SHARE_ANYONE => get_string('archive:sharemode_anyone', 'local_learningprism'),
        ]);
        $mform->setDefault('sharemode', sync_manager::SHARE_GROUP);
        $mform->addHelpButton('sharemode', 'archive:sharemode', 'local_learningprism');

        $mform->addElement('text', 'groupemail', get_string('archive:groupemail', 'local_learningprism'), ['size' => 40]);
        $mform->setType('groupemail', PARAM_EMAIL);
        $mform->hideIf('groupemail', 'sharemode', 'eq', sync_manager::SHARE_PARTICIPANTS);
        $mform->hideIf('groupemail', 'sharemode', 'eq', sync_manager::SHARE_ANYONE);

        $mform->addElement('static', 'participantsnotice', '', get_string('archive:participantsnotice', 'local_learningprism'));
        $mform->hideIf('participantsnotice', 'sharemode', 'neq', sync_manager::SHARE_PARTICIPANTS);

        $mform->addElement('static', 'groupcreatenotice', '', get_string('archive:groupcreatenotice', 'local_learningprism'));
        $mform->hideIf('groupcreatenotice', 'sharemode', 'neq', sync_manager::SHARE_GROUP_CREATE);

        $mform->addElement('static', 'anyonenotice', '', get_string('archive:anyonenotice', 'local_learningprism'));
        $mform->hideIf('anyonenotice', 'sharemode', 'neq', sync_manager::SHARE_ANYONE);

        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'generate', get_string('archive:generate', 'local_learningprism'));
        $buttons[] = $mform->createElement('submit', 'updatesharing', get_string('archive:updatesharing', 'local_learningprism'));
        $buttons[] = $mform->createElement('cancel');
        $mform->addGroup($buttons, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

    /**
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $mode = sync_manager::normalise_sharemode((string) ($data['sharemode'] ?? sync_manager::SHARE_GROUP));
        if ($mode === sync_manager::SHARE_GROUP || $mode === sync_manager::SHARE_GROUP_CREATE) {
            $email = trim((string) ($data['groupemail'] ?? ''));
            if ($email === '' || !validate_email($email)) {
                $errors['groupemail'] = get_string('archive:groupemail_invalid', 'local_learningprism');
            }
        }
        return $errors;
    }
}
