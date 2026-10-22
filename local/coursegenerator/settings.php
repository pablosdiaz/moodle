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
 * Admin settings and report page registration.
 *
 * @package    local_coursegenerator
 * @copyright  2026 Course generator
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_coursegenerator', get_string('pluginname', 'local_coursegenerator'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_coursegenerator/generalheading',
        get_string('settings_general', 'local_coursegenerator'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_coursegenerator/enabled',
        get_string('settings_enabled', 'local_coursegenerator'),
        get_string('settings_enabled_desc', 'local_coursegenerator'),
        1
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_coursegenerator/apikey',
        get_string('settings_apikey', 'local_coursegenerator'),
        get_string('settings_apikey_desc', 'local_coursegenerator'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursegenerator/model',
        get_string('settings_model', 'local_coursegenerator'),
        get_string('settings_model_desc', 'local_coursegenerator'),
        'gemini-2.0-flash',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursegenerator/maxsections',
        get_string('settings_maxsections', 'local_coursegenerator'),
        get_string('settings_maxsections_desc', 'local_coursegenerator'),
        10,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_coursegenerator/maxquestions',
        get_string('settings_maxquestions', 'local_coursegenerator'),
        get_string('settings_maxquestions_desc', 'local_coursegenerator'),
        10,
        PARAM_INT
    ));

    // Add the report/dashboard page under Site administration > Reports.
    $ADMIN->add('reports', new admin_externalpage(
        'local_coursegenerator_dashboard',
        get_string('dashboard', 'local_coursegenerator'),
        new moodle_url('/local/coursegenerator/admin/dashboard.php'),
        'local/coursegenerator:viewreport'
    ));
}
