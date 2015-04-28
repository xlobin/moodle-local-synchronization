<?php

//define('CLI_SCRIPT', 1);
require_once('../../config.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
// $CFG->keeptempdirectoriesonbackup = true;
$course_id = 3; // Set this to one existing choice cmid in your dev site
$user_doing_the_backup = 2; // Set this to the id of your admin accouun

// 
//$bc = new backup_controller(backup::TYPE_1COURSE, $course_id, backup::FORMAT_MOODLE,
//                            backup::INTERACTIVE_NO, backup::MODE_GENERAL, $user_doing_the_backup);
//$bc->execute_plan();

$context = context_course::instance($course_id);
//var_dump($context->id);
$fs = get_file_storage();
echo '<pre>';
$files = $fs->get_area_files($context->id, 'backup', 'course', false, 'timecreated');

$myfiles = array_reverse($files);
$backup_file = '';
foreach ($myfiles as $file){
    
    if ($file->get_filename() === 'backup.mbz'){
        $backup_file = $file;
        break;
    }
}


//var_dump($myfiles);