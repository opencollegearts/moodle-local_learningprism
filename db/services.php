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
 * External services for local_learningprism.
 *
 * @package    local_learningprism
 * @copyright  2026 Open College of the Arts
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_learningprism_get_course_instances' => [
        'classname' => 'local_learningprism\\external\\get_course_instances',
        'description' => 'List convertible course modules and button states for a course.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/learningprism:download',
    ],
    'local_learningprism_get_activity_formats' => [
        'classname' => 'local_learningprism\\external\\get_activity_formats',
        'description' => 'Get format statuses for a single activity.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/learningprism:download',
    ],
    'local_learningprism_request_formats' => [
        'classname' => 'local_learningprism\\external\\request_formats',
        'description' => 'Queue missing format conversions for an activity.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/learningprism:request',
    ],
    'local_learningprism_pregenerate_formats' => [
        'classname' => 'local_learningprism\\external\\pregenerate_formats',
        'description' => 'Pre-generate formats for activities in a course.',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/learningprism:manage',
    ],
    'local_learningprism_get_download_url' => [
        'classname' => 'local_learningprism\\external\\get_download_url',
        'description' => 'Get download URL(s) for ready formats.',
        'type' => 'read',
        'ajax' => true,
        'loginrequired' => true,
        'capabilities' => 'local/learningprism:download',
    ],
];
