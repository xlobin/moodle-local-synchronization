<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/lib/MyClient.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib/course.php');

admin_externalpage_setup('localsynchfromserver');

$course = $DB->get_record('course', array('id' => '3'));
$info = get_fast_modinfo($course);
$cm = $info->get_cm(8);
print_object($cm);