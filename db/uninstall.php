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
 * Post-install code for the synchronization plugin.
 *
 * @package    local
 * @subpackage synchronization
 * @copyright  2015 Arie Dwiyana
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_synchronization_uninstall() {
    global $CFG, $DB;
    if (defined('OVERRIDE_DB_CLASS')) {
        $filePath = $CFG->libdir . '/dmllib.php';
        $dmlFile = file($filePath);
        $dmlFile[0] = '<?php' . "\n";
        $dmlFile[count($dmlFile) - 1] = "";
        file_put_contents($filePath, implode($dmlFile));

        $dbman = $DB->get_manager();
        $isExists = $dbman->table_exists('synch_log_item');
        if ($isExists) {
            $table = new xmldb_table('synch_log_item');
            $dbman->drop_table($table);
        }
    }

    $table = new xmldb_table('course');
    $field = new xmldb_field('sync_version', XMLDB_TYPE_INTEGER, 11, null, null, null, 0);
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
    }

    $listTables = array(
        'course_sections', 'course_modules',
    );
    
    foreach ($listTables as $key => $table) {
        $table = new xmldb_table($table);
        $field = new xmldb_field('my_id', XMLDB_TYPE_INTEGER, 11, null, null, null, 0);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
    }

    $modules = $DB->get_records('modules', array('visible' => 1));
    foreach ($modules as $key => $values) {
        $table = new xmldb_table($values->name);
        $field = new xmldb_field('my_id', XMLDB_TYPE_INTEGER, 11, null, null, null, 0);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
    }
}
