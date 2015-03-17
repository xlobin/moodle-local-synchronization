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
 * This file keeps track of upgrades to
 * the forum module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package local-synchronization
 * @copyright 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_synchronization_upgrade($oldversion) {
    global $DB;

    if ($oldversion == 2015010100) {
        $dbman = $DB->get_manager();
        $listTables = array(
            'course_sections', 'course_modules',
        );
        foreach ($listTables as $key => $table) {
            $table = new xmldb_table($table);
            $field = new xmldb_field('my_id', XMLDB_TYPE_INTEGER, 11, null, null, null, 0);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
                $dbman->add_field($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }
        }

        $modules = $DB->get_records('modules', array('visible' => 1));
        foreach ($modules as $key => $values) {
            $table = new xmldb_table($values->name);
            $field = new xmldb_field('my_id', XMLDB_TYPE_INTEGER, 11, null, null, null, 0);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
                $dbman->add_field($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }
        }
    }

    return true;
}
