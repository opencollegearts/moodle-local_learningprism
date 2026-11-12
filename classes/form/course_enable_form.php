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

use local_learningprism\local\tts\voice_catalog;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Course Learning Prism settings form.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_enable_form extends \moodleform {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('advcheckbox', 'enabled', get_string('course:enable', 'local_learningprism'));
        $mform->addHelpButton('enabled', 'course:enable', 'local_learningprism');

        $mform->addElement(
            'advcheckbox',
            'allowstudentaudio',
            get_string('course:allowstudentaudio', 'local_learningprism')
        );
        $mform->addHelpButton('allowstudentaudio', 'course:allowstudentaudio', 'local_learningprism');
        $mform->disabledIf('allowstudentaudio', 'enabled');

        $voiceoptions = ['' => get_string('course:defaultvoice_site', 'local_learningprism')];
        foreach (voice_catalog::options_for_provider(voice_catalog::active_provider()) as $id => $label) {
            $voiceoptions[$id] = $label;
        }
        $mform->addElement(
            'select',
            'defaultvoice',
            get_string('course:defaultvoice', 'local_learningprism'),
            $voiceoptions
        );
        $mform->addHelpButton('defaultvoice', 'course:defaultvoice', 'local_learningprism');
        $mform->disabledIf('defaultvoice', 'enabled');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
