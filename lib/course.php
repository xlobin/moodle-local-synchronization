<?php
defined('MOODLE_INTERNAL') || die();
/**
 * Create a course and either return a $course object
 *
 * Please note this functions does not verify any access control,
 * the calling code is responsible for all validation (usually it is the form definition).
 *
 * @param array $editoroptions course description editor options
 * @param object $data  - all the data needed for an entry in the 'course' table
 * @return object new course instance
 */
function create_my_course($data, $editoroptions = NULL) {
    global $DB;

    //check the categoryid - must be given for all new courses
    $category = $DB->get_record('course_categories', array('id'=>$data->category), '*', MUST_EXIST);

    // Check if the shortname already exists.
    if (!empty($data->shortname)) {
        if ($DB->record_exists('course', array('shortname' => $data->shortname))) {
            throw new moodle_exception('shortnametaken', '', '', $data->shortname);
        }
    }

    // Check if the idnumber already exists.
    if (!empty($data->idnumber)) {
        if ($DB->record_exists('course', array('idnumber' => $data->idnumber))) {
            throw new moodle_exception('courseidnumbertaken', '', '', $data->idnumber);
        }
    }

    // Check if timecreated is given.
    $data->timecreated  = !empty($data->timecreated) ? $data->timecreated : time();
    $data->timemodified = $data->timecreated;

    // place at beginning of any category
    $data->sortorder = 0;

    if ($editoroptions) {
        // summary text is updated later, we need context to store the files first
        $data->summary = '';
        $data->summary_format = FORMAT_HTML;
    }

    if (!isset($data->visible)) {
        // data not from form, add missing visibility info
        $data->visible = $category->visible;
    }
    $data->visibleold = $data->visible;

    $newcourseid = $DB->insert_record('course', $data);
    if (isset($data->id)){
        $myQuery= "update {course} set id={$data->id} where id=$newcourseid";
        $DB->execute($myQuery);
        $newcourseid = $data->id;
    }
    $context = context_course::instance($newcourseid, MUST_EXIST);

    if ($editoroptions) {
        // Save the files used in the summary editor and store
        $data = file_postupdate_standard_editor($data, 'summary', $editoroptions, $context, 'course', 'summary', 0);
        $DB->set_field('course', 'summary', $data->summary, array('id'=>$newcourseid));
        $DB->set_field('course', 'summaryformat', $data->summary_format, array('id'=>$newcourseid));
    }
    if ($overviewfilesoptions = course_overviewfiles_options($newcourseid)) {
        // Save the course overviewfiles
        $data = file_postupdate_standard_filemanager($data, 'overviewfiles', $overviewfilesoptions, $context, 'course', 'overviewfiles', 0);
    }

    // update course format options
    course_get_format($newcourseid)->update_course_format_options($data);

    $course = course_get_format($newcourseid)->get_course();

    // Setup the blocks
    blocks_add_default_course_blocks($course);

    // Create a default section.
    course_create_sections_if_missing($course, 0);

    fix_course_sortorder();
    // purge appropriate caches in case fix_course_sortorder() did not change anything
    cache_helper::purge_by_event('changesincourse');

    // new context created - better mark it as dirty
    $context->mark_dirty();

    // Save any custom role names.
    save_local_role_names($course->id, (array)$data);

    // set up enrolments
    enrol_course_updated(true, $course, $data);

    // Trigger a course created event.
    $event = \core\event\course_created::create(array(
        'objectid' => $course->id,
        'context' => context_course::instance($course->id),
        'other' => array('shortname' => $course->shortname,
                         'fullname' => $course->fullname)
    ));
    $event->trigger();

    return $course;
}