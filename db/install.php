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

    $dbman = $DB->get_manager();

    $table = new xmldb_table('course');
    $field = new xmldb_field('sync_version', XMLDB_TYPE_INTEGER, 11, null, null, null, 0);
    if ($dbman->field_exists($table, $field)) {
        $dbman->drop_field($table, $field);
    } else {
        $dbman->add_field($table, $field);
    }

    $listTables = array(
        'course_sections', 
        'course_modules', 
        'course_categories',
        'quiz_slots',
        'question',
        'question_answers',
        'question_hints',
        'question_truefalse',
        'question_numerical',
        'question_numerical_options',
        'question_numerical_units',
        'question_calculated_options',
        'question_calculated',
        'qtype_multichoice_options',
        'question_multianswer',
        'qtype_randomsamatch_options',
        'qtype_shortanswer_options',
        'qtype_essay_options',
        'qtype_match_subquestions',
        'qtype_match_options',
        'question_datasets',
        'quiz_feedback',
        'book_chapters',
        'choice_options',
        'glossary_categories',
        'glossary_entries',
        'glossary_alias',
        'glossary_entries_categories',
        'lesson_pages',
        'lesson_answers',
        'wiki_subwikis',
        'wiki_pages',
        'wiki_versions',
        'wiki_links',
        'forum_discussions',
        'forum_posts',
        'workshop_old',
        'workshop_elements_old',
        'workshop_grades_old',
        'workshop_rubrics_old',
        'workshop_stockcomments_old',
        'workshop_submissions',
        'workshop_assessments',
        'workshop_grades',
        'workshopallocation_scheduled',
        'workshopeval_best_settings',
        'workshopform_accumulative',
        'workshopform_comments',
        'workshopform_numerrors',
        'workshopform_numerrors_map',
        'workshopform_rubric',
        'workshopform_rubric_levels',
        'workshopform_rubric_config',
        'feedback_item',
        'feedback_sitecourse_map',
        'feedback_template',
        'feedback_value',
        'feedback_valuetmp',
        'scorm_scoes',
        'scorm_scoes_data',
        'scorm_seq_mapinfo',
        'scorm_seq_objective',
        'scorm_seq_rolluprule',
        'scorm_seq_rolluprulecond',
        'scorm_seq_rulecond',
        'scorm_seq_ruleconds',
    );

    foreach ($listTables as $key => $table) {
        $table = new xmldb_table($table);
        $field = new xmldb_field('my_id', XMLDB_TYPE_INTEGER, 11, null, null, null, 0);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $dbman->add_field($table, $field);
    }

    $modules = $DB->get_records('modules', array('visible' => 1));
    foreach ($modules as $key => $values) {
        $table = new xmldb_table($values->name);
        $field = new xmldb_field('my_id', XMLDB_TYPE_INTEGER, 11, null, null, null, 0);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        $dbman->add_field($table, $field);
    }
}
