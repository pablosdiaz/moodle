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
 * Teacher panel: generate AI content for a course.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use local_coursegenerator\ai_client;
use local_coursegenerator\content_generator;
use local_coursegenerator\log_manager;
use local_coursegenerator\form\generate_form;

$id = required_param('id', PARAM_INT);

$course = get_course($id);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/coursegenerator:generate', $context);

$url = new moodle_url('/local/coursegenerator/teacher/index.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('teacherpanel', 'local_coursegenerator'));
$PAGE->set_heading($course->fullname);

$modinfo = get_fast_modinfo($course);
$sectionoptions = [];
foreach ($modinfo->get_section_info_all() as $sectionnum => $sectioninfo) {
    $name = get_section_name($course, $sectioninfo);
    $sectionoptions[$sectionnum] = $name;
}
$sectionoptions[generate_form::NEW_SECTION] = get_string('newsection', 'local_coursegenerator');

$maxsections = (int) get_config('local_coursegenerator', 'maxsections') ?: 10;
$maxquestions = (int) get_config('local_coursegenerator', 'maxquestions') ?: 10;

$form = new generate_form($url, [
    'course' => $course,
    'sectionoptions' => $sectionoptions,
    'maxsections' => $maxsections,
    'maxquestions' => $maxquestions,
]);

$enabled = (bool) get_config('local_coursegenerator', 'enabled');
$configured = ai_client::is_configured();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

if ($enabled && $configured && ($data = $form->get_data())) {
    require_sesskey();

    $topic = trim($data->topic);

    try {
        switch ($data->contenttype) {
            case 'sections':
                $names = content_generator::generate_sections($course->id, $topic, (int) $data->numsections, $USER->id);
                $message = get_string('success_sections', 'local_coursegenerator', count($names));
                break;

            case 'page':
                $sectionnum = (int) $data->sectionnum;
                if ($sectionnum === generate_form::NEW_SECTION) {
                    $format = course_get_format($course);
                    $sectionnum = max(0, (int) $format->get_last_section_number()) + 1;
                    course_create_sections_if_missing($course, [$sectionnum]);
                }
                $moduleinfo = content_generator::generate_page(
                    $course->id,
                    $sectionnum,
                    trim($data->title),
                    $topic,
                    $USER->id
                );
                $message = get_string('success_page', 'local_coursegenerator', $moduleinfo->name);
                break;

            case 'quiz':
                $sectionnum = (int) $data->sectionnum;
                if ($sectionnum === generate_form::NEW_SECTION) {
                    $format = course_get_format($course);
                    $sectionnum = max(0, (int) $format->get_last_section_number()) + 1;
                    course_create_sections_if_missing($course, [$sectionnum]);
                }
                $result = content_generator::generate_quiz(
                    $course->id,
                    $sectionnum,
                    trim($data->title),
                    $topic,
                    (int) $data->numquestions,
                    $USER->id
                );
                $message = get_string('success_quiz', 'local_coursegenerator', $result);
                break;

            default:
                throw new moodle_exception('error_aiempty', 'local_coursegenerator');
        }

        rebuild_course_cache($course->id, true);
        redirect($url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
    } catch (\Throwable $e) {
        log_manager::log_error($course->id, $USER->id, (string) $data->contenttype, $topic, $e->getMessage());
        redirect(
            $url,
            get_string('error_generation', 'local_coursegenerator', $e->getMessage()),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('teacherpanel_heading', 'local_coursegenerator', format_string($course->fullname)));
echo html_writer::tag('p', get_string('teacherpanel_intro', 'local_coursegenerator'));

if (!$enabled) {
    echo $OUTPUT->notification(get_string('form_disabled', 'local_coursegenerator'), 'info');
} else if (!$configured) {
    echo $OUTPUT->notification(get_string('form_noapikey', 'local_coursegenerator'), 'warning');
} else {
    $form->display();
}

$recent = log_manager::get_recent(10, $course->id, $USER->id);
if ($recent) {
    echo $OUTPUT->heading(get_string('recentgenerations', 'local_coursegenerator'), 4);

    $table = new html_table();
    $table->head = [
        get_string('col_type', 'local_coursegenerator'),
        get_string('col_items', 'local_coursegenerator'),
        get_string('col_topic', 'local_coursegenerator'),
        get_string('col_date', 'local_coursegenerator'),
        get_string('col_status', 'local_coursegenerator'),
    ];
    foreach ($recent as $row) {
        $status = $row->status === 'success'
            ? $OUTPUT->notification(get_string('status_success', 'local_coursegenerator'), 'success', false)
            : $OUTPUT->notification(get_string('status_error', 'local_coursegenerator'), 'error', false);
        $table->data[] = [
            get_string('contenttype_' . $row->contenttype, 'local_coursegenerator'),
            $row->itemcount,
            format_string(shorten_text($row->topic ?? '', 80)),
            userdate($row->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
            $status,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
