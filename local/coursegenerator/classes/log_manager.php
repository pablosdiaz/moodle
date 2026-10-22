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

namespace local_coursegenerator;

defined('MOODLE_INTERNAL') || die();

/**
 * Records generation events and provides the aggregated data used by the admin report.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_manager {

    /** @var string */
    const TABLE = 'local_coursegenerator_log';

    /**
     * Record a successful generation.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $contenttype sections|page|quiz
     * @param string $itemname
     * @param int $itemcount
     * @param string $topic
     * @param string $aimodel
     */
    public static function log(
        int $courseid,
        int $userid,
        string $contenttype,
        string $itemname,
        int $itemcount,
        string $topic,
        string $aimodel
    ): void {
        self::insert($courseid, $userid, $contenttype, $itemname, $itemcount, $topic, $aimodel, 'success', null);
    }

    /**
     * Record a failed generation attempt.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $contenttype
     * @param string $topic
     * @param string $errormessage
     */
    public static function log_error(int $courseid, int $userid, string $contenttype, string $topic, string $errormessage): void {
        self::insert($courseid, $userid, $contenttype, '', 0, $topic, '', 'error', $errormessage);
    }

    /**
     * @param int $courseid
     * @param int $userid
     * @param string $contenttype
     * @param string $itemname
     * @param int $itemcount
     * @param string $topic
     * @param string $aimodel
     * @param string $status
     * @param string|null $errormessage
     */
    protected static function insert(
        int $courseid,
        int $userid,
        string $contenttype,
        string $itemname,
        int $itemcount,
        string $topic,
        string $aimodel,
        string $status,
        ?string $errormessage
    ): void {
        global $DB;

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->userid = $userid;
        $record->contenttype = $contenttype;
        $record->itemname = shorten_text($itemname, 255);
        $record->itemcount = $itemcount;
        $record->topic = $topic;
        $record->aimodel = $aimodel;
        $record->status = $status;
        $record->errormessage = $errormessage;
        $record->timecreated = time();

        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * High level totals for the dashboard stat tiles.
     *
     * @return \stdClass
     */
    public static function get_summary(): \stdClass {
        global $DB;

        $summary = new \stdClass();
        $summary->total = (int) $DB->count_records(self::TABLE, ['status' => 'success']);
        $summary->totalitems = (int) ($DB->get_field_sql(
            'SELECT SUM(itemcount) FROM {' . self::TABLE . '} WHERE status = ?', ['success']
        ) ?: 0);
        $summary->courses = (int) ($DB->get_field_sql(
            'SELECT COUNT(DISTINCT courseid) FROM {' . self::TABLE . '} WHERE status = ?', ['success']
        ) ?: 0);
        $summary->teachers = (int) ($DB->get_field_sql(
            'SELECT COUNT(DISTINCT userid) FROM {' . self::TABLE . '} WHERE status = ?', ['success']
        ) ?: 0);

        return $summary;
    }

    /**
     * Number of successful generations per content type.
     *
     * @return array contenttype => count
     */
    public static function get_counts_by_type(): array {
        global $DB;

        $rows = $DB->get_records_sql(
            'SELECT contenttype, COUNT(*) AS cnt FROM {' . self::TABLE . '} WHERE status = ? GROUP BY contenttype',
            ['success']
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row->contenttype] = (int) $row->cnt;
        }
        return $result;
    }

    /**
     * Number of successful generations per day, for the last $days days.
     *
     * @param int $days
     * @return array ['labels' => [...], 'values' => [...]]
     */
    public static function get_daily_counts(int $days = 30): array {
        global $DB;

        $since = strtotime('-' . ($days - 1) . ' days', usergetmidnight(time()));

        $rows = $DB->get_records_sql(
            'SELECT timecreated FROM {' . self::TABLE . '} WHERE status = ? AND timecreated >= ?',
            ['success', $since]
        );

        $counts = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $since + $i * DAYSECS;
            $counts[userdate($day, '%Y-%m-%d')] = 0;
        }
        foreach ($rows as $row) {
            $key = userdate($row->timecreated, '%Y-%m-%d');
            if (isset($counts[$key])) {
                $counts[$key]++;
            }
        }

        return [
            'labels' => array_map(static fn($d) => userdate(strtotime($d), '%d %b'), array_keys($counts)),
            'values' => array_values($counts),
        ];
    }

    /**
     * Content generated per teacher.
     *
     * @param int $limit
     * @return array of stdClass {userid, fullname, generations, items}
     */
    public static function get_by_teacher(int $limit = 20): array {
        global $DB;

        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT l.userid, {$userfields}, COUNT(*) AS generations, SUM(l.itemcount) AS items
                  FROM {" . self::TABLE . "} l
                  JOIN {user} u ON u.id = l.userid
                 WHERE l.status = :status
              GROUP BY l.userid, {$userfields}
              ORDER BY generations DESC";

        $rows = $DB->get_records_sql($sql, ['status' => 'success'], 0, $limit);
        foreach ($rows as $row) {
            $row->fullname = fullname($row);
        }
        return array_values($rows);
    }

    /**
     * Content generated per course.
     *
     * @param int $limit
     * @return array of stdClass {courseid, fullname, generations, items}
     */
    public static function get_by_course(int $limit = 20): array {
        global $DB;

        $sql = "SELECT l.courseid, c.fullname, COUNT(*) AS generations, SUM(l.itemcount) AS items
                  FROM {" . self::TABLE . "} l
                  JOIN {course} c ON c.id = l.courseid
                 WHERE l.status = :status
              GROUP BY l.courseid, c.fullname
              ORDER BY generations DESC";

        $rows = $DB->get_records_sql($sql, ['status' => 'success'], 0, $limit);
        return array_values($rows);
    }

    /**
     * Most recent generation events (any status), for the activity table.
     *
     * @param int $limit
     * @param int|null $courseid restrict to a single course.
     * @param int|null $userid restrict to a single user.
     * @return array of stdClass
     */
    public static function get_recent(int $limit = 20, ?int $courseid = null, ?int $userid = null): array {
        global $DB;

        $params = [];
        $where = [];
        if ($courseid !== null) {
            $where[] = 'l.courseid = :courseid';
            $params['courseid'] = $courseid;
        }
        if ($userid !== null) {
            $where[] = 'l.userid = :userid';
            $params['userid'] = $userid;
        }
        $wheresql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $userfields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;
        $sql = "SELECT l.*, c.fullname AS coursename, {$userfields}
                  FROM {" . self::TABLE . "} l
                  JOIN {course} c ON c.id = l.courseid
                  JOIN {user} u ON u.id = l.userid
                  {$wheresql}
              ORDER BY l.timecreated DESC";

        $rows = $DB->get_records_sql($sql, $params, 0, $limit);
        foreach ($rows as $row) {
            $row->fullname = fullname($row);
        }
        return array_values($rows);
    }
}
