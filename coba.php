<?php

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/lib/MyClient.php');
require_once($CFG->libdir . '/filelib.php');
require_once(__DIR__ . '/lib/course.php');

admin_externalpage_setup('localsynchfromserver');

$sections = $DB->get_records('course_sections', array('course' => 2));
foreach ($sections as $key => $sec) {
    if ($sec->sequence != "" || $sec->sequence) {
        $modlist = explode(',', $sec->sequence);
        $modules = array();
        foreach ($modlist as $mod) {
            $module = $DB->get_record('course_modules', array('id' => $mod));
            $mod_type = $DB->get_record('modules', array('id' => $module->module));
            $module->module_data[$mod_type->name] = $DB->get_records($mod_type->name, array('id' => $module->instance));
            $modules[$module->id] = $module;
        }
        $sec->modules = $modules;
    }
    $sections[$key] = $sec;
}
print_object($sections);
echo "<hr/>";
exit();
