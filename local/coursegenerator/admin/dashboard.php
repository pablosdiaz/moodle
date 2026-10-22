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
 * Admin report: how much content has been generated with the AI course generator.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_coursegenerator\log_manager;

admin_externalpage_setup('local_coursegenerator_dashboard');

$summary = log_manager::get_summary();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('dashboard_heading', 'local_coursegenerator'));
echo html_writer::tag('p', get_string('dashboard_intro', 'local_coursegenerator'));

// Stat tiles.
$tiles = [
    ['stat_total', $summary->total],
    ['stat_totalitems', $summary->totalitems],
    ['stat_courses', $summary->courses],
    ['stat_teachers', $summary->teachers],
];
echo html_writer::start_div('local-coursegenerator-tiles', ['style' => 'display:flex;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;']);
foreach ($tiles as [$stringkey, $value]) {
    echo html_writer::div(
        html_writer::div(get_string($stringkey, 'local_coursegenerator'), 'text-muted small')
        . html_writer::div((string) $value, 'display-6'),
        'card p-3',
        ['style' => 'min-width:180px;']
    );
}
echo html_writer::end_div();

if ($summary->total === 0) {
    echo $OUTPUT->notification(get_string('nodata', 'local_coursegenerator'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Chart: generations by content type.
$bytype = log_manager::get_counts_by_type();
if ($bytype) {
    $labels = [];
    $values = [];
    foreach ($bytype as $type => $count) {
        $labels[] = get_string('contenttype_' . $type, 'local_coursegenerator');
        $values[] = $count;
    }
    $chart = new \core\chart_bar();
    $chart->set_labels($labels);
    $series = new \core\chart_series(get_string('chart_bytype', 'local_coursegenerator'), $values);
    $chart->add_series($series);

    echo $OUTPUT->heading(get_string('chart_bytype', 'local_coursegenerator'), 4);
    echo $OUTPUT->render($chart);
}

// Chart: generations over time.
$daily = log_manager::get_daily_counts(30);
$chart = new \core\chart_line();
$chart->set_labels($daily['labels']);
$series = new \core\chart_series(get_string('chart_overtime', 'local_coursegenerator'), $daily['values']);
$series->set_smooth(true);
$chart->add_series($series);

echo $OUTPUT->heading(get_string('chart_overtime', 'local_coursegenerator'), 4);
echo $OUTPUT->render($chart);

// Table: by teacher.
$byteacher = log_manager::get_by_teacher();
if ($byteacher) {
    echo $OUTPUT->heading(get_string('table_byteacher', 'local_coursegenerator'), 4);
    $table = new html_table();
    $table->head = [
        get_string('col_teacher', 'local_coursegenerator'),
        get_string('col_count', 'local_coursegenerator'),
        get_string('col_items', 'local_coursegenerator'),
    ];
    foreach ($byteacher as $row) {
        $table->data[] = [$row->fullname, $row->generations, $row->items];
    }
    echo html_writer::table($table);
}

// Table: by course.
$bycourse = log_manager::get_by_course();
if ($bycourse) {
    echo $OUTPUT->heading(get_string('table_bycourse', 'local_coursegenerator'), 4);
    $table = new html_table();
    $table->head = [
        get_string('col_course', 'local_coursegenerator'),
        get_string('col_count', 'local_coursegenerator'),
        get_string('col_items', 'local_coursegenerator'),
    ];
    foreach ($bycourse as $row) {
        $courseurl = new moodle_url('/course/view.php', ['id' => $row->courseid]);
        $table->data[] = [
            html_writer::link($courseurl, format_string($row->fullname)),
            $row->generations,
            $row->items,
        ];
    }
    echo html_writer::table($table);
}

// Table: most recent activity across the whole site.
$recent = log_manager::get_recent(30);
if ($recent) {
    echo $OUTPUT->heading(get_string('table_recent', 'local_coursegenerator'), 4);
    $table = new html_table();
    $table->head = [
        get_string('col_date', 'local_coursegenerator'),
        get_string('col_teacher', 'local_coursegenerator'),
        get_string('col_course', 'local_coursegenerator'),
        get_string('col_type', 'local_coursegenerator'),
        get_string('col_items', 'local_coursegenerator'),
        get_string('col_topic', 'local_coursegenerator'),
        get_string('col_status', 'local_coursegenerator'),
    ];
    foreach ($recent as $row) {
        $courseurl = new moodle_url('/course/view.php', ['id' => $row->courseid]);
        $status = $row->status === 'success'
            ? $OUTPUT->notification(get_string('status_success', 'local_coursegenerator'), 'success', false)
            : $OUTPUT->notification(get_string('status_error', 'local_coursegenerator'), 'error', false);
        $table->data[] = [
            userdate($row->timecreated, get_string('strftimedatetimeshort', 'core_langconfig')),
            $row->fullname,
            html_writer::link($courseurl, format_string($row->coursename)),
            get_string('contenttype_' . $row->contenttype, 'local_coursegenerator'),
            $row->itemcount,
            format_string(shorten_text((string) $row->topic, 60)),
            $status,
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
