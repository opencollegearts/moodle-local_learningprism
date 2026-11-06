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

namespace local_learningprism\task;

use local_learningprism\local\notebook\settings;
use local_learningprism\local\notebook\sync_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Sync course Google Notebook sources (googleuploadsperrun uploads per run).
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_notebooks extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task:sync_notebooks', 'local_learningprism');
    }

    public function execute(): void {
        if (!settings::enabled()) {
            return;
        }
        $count = sync_manager::process_one();
        if (!$count) {
            mtrace('local_learningprism notebook: no queued work');
        }
    }
}
