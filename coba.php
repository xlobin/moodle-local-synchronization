<?php

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/lib/MyClient.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib/course.php');

$log = get_legacy_logdata();
var_dump($log);
exit();
//$module = get_fast_modinfo(2);
//$course = $module->get_cm(4);
//echo "<pre>";
//var_dump($course);