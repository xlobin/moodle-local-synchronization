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
 * @copyright  2015 Muhammad Bahjah
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_synchronization_install() {
    global $CFG, $DB;
    if (!defined('OVERRIDE_DB_CLASS')) {
        $filePath = $CFG->libdir . '/dmllib.php';
        $dmlFile = file($filePath);
        $dmlFile[0] = '<?php $filename = $CFG->dirroot . \'/local/synchronization/lib/dmllib.php\';if (!file_exists($filename)) {'."\n";
        $dmlFile[count($dmlFile)] = '} else {define(\'OVERRIDE_DB_CLASS\', true);require_once $filename;}';
        file_put_contents($filePath, implode($dmlFile));
        $dbman = $DB->get_manager();
        
        $table = new xmldb_table('synch_log_item');
        $table->add_field('id', XMLDB_TYPE_INTEGER, 11, null, true, true);
        $table->add_field('table_name', XMLDB_TYPE_CHAR, '50', null, true);
        $table->add_field('qtype', XMLDB_TYPE_CHAR, '20', null, true);
        $table->add_field('sqltext', XMLDB_TYPE_TEXT, null, null, true);
        $table->add_field('sqlparams', XMLDB_TYPE_TEXT, null, null, true);
        $table->add_field('timelogged', XMLDB_TYPE_INTEGER, 11, null, true);
        $table->add_field('status', XMLDB_TYPE_INTEGER, 2, null, true, null, 0);
        $table->add_key('synch_log_item_pk', XMLDB_KEY_PRIMARY, array('id'));
        if ($dbman->table_exists($table)){
            $dbman->drop_table($table);
        }
        $dbman->create_table($table);
    } 
}
