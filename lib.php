<?php

//function local_synchronization_extends_settings_navigation(settings_navigation $settingsnav, $context) {
//    global $CFG, $PAGE, $USER;
//
//    $admins = get_admins();
//    $isadmin = false;
//    foreach ($admins as $admin) {
//        if ($USER->id == $admin->id) {
//            $isadmin = true;
//            break;
//        }
//    }
//    
//    if ($isadmin) {
//        $nodeFoo = $settingsnav->prepend(get_string('pluginname', 'local_synchronization'));
//
//        $listNodes = array(
//            array(
//                'url' => new moodle_url('/local/synchronization/database_backup.php'),
//                'text' => get_string('database_backup', 'local_synchronization')
//            ),
//            array(
//                'url' => '/',
//                'text' => get_string('synchronize_with_server', 'local_synchronization')
//            ),
//            array(
//                'url' => new moodle_url('/admin/settings.php', array('section' => 'local_synchronization')),
//                'text' => get_string('setting', 'local_synchronization')
//            ),
//        );
//
//        foreach ($listNodes as $row) {
//            $nodeFoo->add($row['text'], $row['url']);
//        }
//    }
//}
