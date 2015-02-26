<?php

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir. '/coursecatlib.php');

//admin_externalpage_setup('localsynchronization');
//$course = $DB->get_record('course', array('id' => 12));

var_dump($course);
//$overviewfiles = course_overviewfiles_options($course);
//$course->get_course_overviewfiles()
//$course = get_course('12')->get_course_overviewfiles();
//course
get_course('12');
var_dump($course);

exit();
$record = $DB->get_record('files', array('id' => 60));
$record2 = json_decode(file_get_contents('test'));
var_dump($record2);
//$test = file_put_contents('test', json_encode($record2));
//var_dump($test);
//$record = $record2;
$record2->id = 60;
//var_dump($record);
//exit();
$fileStorage = get_file_storage();

//var_dump($record);
$fileStorage->create_file_from_string($record2, 'aaabsd');
//exit();
//var_dump($fileStorage);