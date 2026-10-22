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
 * Entry point for teachers: pick which of their courses to generate AI content for.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$url = new moodle_url('/local/coursegenerator/index.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('selectcourse_heading', 'local_coursegenerator'));
$PAGE->set_heading(get_string('selectcourse_heading', 'local_coursegenerator'));

$courses = get_user_capability_course('local/coursegenerator:generate', null, true, 'fullname,shortname,visible', 'fullname');
$courses = array_filter((array) $courses, static function ($course) {
    return $course->id != SITEID && $course->visible;
});

// If the teacher only has one course, skip the picker entirely.
if (count($courses) === 1) {
    $only = reset($courses);
    redirect(new moodle_url('/local/coursegenerator/teacher/index.php', ['id' => $only->id]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('selectcourse_heading', 'local_coursegenerator'));
echo html_writer::tag('p', get_string('selectcourse_intro', 'local_coursegenerator'));

if (empty($courses)) {
    echo $OUTPUT->notification(get_string('nocourses', 'local_coursegenerator'), 'info');
} else {
    echo html_writer::start_div('local-coursegenerator-courselist', ['style' => 'display:flex;flex-wrap:wrap;gap:1rem;']);
    foreach ($courses as $course) {
        $generatorurl = new moodle_url('/local/coursegenerator/teacher/index.php', ['id' => $course->id]);
        echo html_writer::start_div('card p-3', ['style' => 'min-width:260px;max-width:320px;']);
        echo html_writer::tag('h5', format_string($course->fullname));
        echo html_writer::tag('p', format_string($course->shortname), ['class' => 'text-muted small']);
        echo html_writer::link($generatorurl, get_string('opencourse', 'local_coursegenerator'), ['class' => 'btn btn-primary']);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
