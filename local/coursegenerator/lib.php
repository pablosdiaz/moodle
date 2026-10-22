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
 * Add a top-level "AI course generator" link to the main navigation, for any user who
 * can generate content in at least one course.
 *
 * @param global_navigation $navigation
 */
function local_coursegenerator_extend_navigation(global_navigation $navigation) {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $courses = get_user_capability_course('local/coursegenerator:generate', null, true, '', '', 1);
    if (empty($courses)) {
        return;
    }

    $navigation->add(
        get_string('navnode', 'local_coursegenerator'),
        new moodle_url('/local/coursegenerator/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_coursegenerator',
        new pix_icon('i/report', '')
    );
}

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

/**
 * Adds the text-to-speech button (with pause/resume) under the educational
 * content and question blocks that content_generator::generate_page() creates.
 *
 * @return string always empty, this callback only queues JS.
 */
function local_coursegenerator_before_footer() {
    global $PAGE;

    if (!$PAGE->cm || $PAGE->cm->modname !== 'page') {
        return '';
    }

    $headings = [
        'content' => get_string('sectioncontent', 'local_coursegenerator'),
        'question' => get_string('sectionquestion', 'local_coursegenerator'),
    ];
    $labels = [
        'play' => get_string('tts_play', 'local_coursegenerator'),
        'pause' => get_string('tts_pause', 'local_coursegenerator'),
        'resume' => get_string('tts_resume', 'local_coursegenerator'),
    ];

    $PAGE->requires->js_call_amd('local_coursegenerator/tts', 'init', [$headings, $labels]);

    return '';
}
