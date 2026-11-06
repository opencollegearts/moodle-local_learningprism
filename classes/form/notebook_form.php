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

use local_learningprism\local\notebook\sync_manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Course Google Notebook generate / share form.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notebook_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'sharemode', get_string('notebook:sharemode', 'local_learningprism'), [
            sync_manager::SHARE_GROUP => get_string('notebook:sharemode_group', 'local_learningprism'),
            sync_manager::SHARE_PARTICIPANTS => get_string('notebook:sharemode_participants', 'local_learningprism'),
        ]);
        $mform->setDefault('sharemode', sync_manager::SHARE_GROUP);
        $mform->addHelpButton('sharemode', 'notebook:sharemode', 'local_learningprism');

        $mform->addElement('text', 'groupemail', get_string('notebook:groupemail', 'local_learningprism'), ['size' => 40]);
        $mform->setType('groupemail', PARAM_EMAIL);
        $mform->hideIf('groupemail', 'sharemode', 'neq', sync_manager::SHARE_GROUP);

        $mform->addElement('static', 'participantsnotice', '', get_string('notebook:participantsnotice', 'local_learningprism'));
        $mform->hideIf('participantsnotice', 'sharemode', 'neq', sync_manager::SHARE_PARTICIPANTS);

        $buttons = [];
        $buttons[] = $mform->createElement('submit', 'generate', get_string('notebook:generate', 'local_learningprism'));
        $buttons[] = $mform->createElement('submit', 'updatesharing', get_string('notebook:updatesharing', 'local_learningprism'));
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
        $mode = $data['sharemode'] ?? sync_manager::SHARE_GROUP;
        if ($mode === sync_manager::SHARE_GROUP) {
            $email = trim((string) ($data['groupemail'] ?? ''));
            if ($email === '' || !validate_email($email)) {
                $errors['groupemail'] = get_string('notebook:groupemail_invalid', 'local_learningprism');
            }
        }
        return $errors;
    }
}
