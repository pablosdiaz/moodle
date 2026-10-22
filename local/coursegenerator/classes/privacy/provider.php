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

namespace local_coursegenerator\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy subsystem implementation for local_coursegenerator.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_coursegenerator_log',
            [
                'userid' => 'privacy:metadata:local_coursegenerator_log:userid',
                'courseid' => 'privacy:metadata:local_coursegenerator_log:courseid',
                'contenttype' => 'privacy:metadata:local_coursegenerator_log:contenttype',
                'itemname' => 'privacy:metadata:local_coursegenerator_log:itemname',
                'topic' => 'privacy:metadata:local_coursegenerator_log:topic',
                'timecreated' => 'privacy:metadata:local_coursegenerator_log:timecreated',
            ],
            'privacy:metadata:local_coursegenerator_log'
        );

        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {local_coursegenerator_log} l
                  JOIN {context} ctx ON ctx.instanceid = l.courseid AND ctx.contextlevel = :contextlevel
                 WHERE l.userid = :userid";

        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $sql = "SELECT l.userid
                  FROM {local_coursegenerator_log} l
                 WHERE l.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (!$contextlist->count()) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $records = $DB->get_records('local_coursegenerator_log', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);

            if (!$records) {
                continue;
            }

            $data = array_map(static function ($record) {
                return (object) [
                    'contenttype' => $record->contenttype,
                    'itemname' => $record->itemname,
                    'itemcount' => $record->itemcount,
                    'topic' => $record->topic,
                    'status' => $record->status,
                    'timecreated' => \core_privacy\local\request\transform::datetime((int) $record->timecreated),
                ];
            }, array_values($records));

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_coursegenerator')],
                (object) ['generations' => $data]
            );
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $DB->delete_records('local_coursegenerator_log', ['courseid' => $context->instanceid]);
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $DB->delete_records('local_coursegenerator_log', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        foreach ($userlist->get_userids() as $userid) {
            $DB->delete_records('local_coursegenerator_log', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
        }
    }
}
