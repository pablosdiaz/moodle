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
 * Library callbacks.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Add a link to the AI course generator into the course navigation/administration menu.
 *
 * @param navigation_node $coursenode
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function local_coursegenerator_extend_navigation_course($coursenode, $course, $coursecontext) {
    if ($course->id == SITEID) {
        return;
    }

    if (!has_capability('local/coursegenerator:generate', $coursecontext)) {
        return;
    }

    $url = new moodle_url('/local/coursegenerator/teacher/index.php', ['id' => $course->id]);
    $coursenode->add(
        get_string('navnode', 'local_coursegenerator'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_coursegenerator',
        new pix_icon('i/report', '')
    );
}
