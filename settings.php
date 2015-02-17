<?php

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    require_once($CFG->dirroot . '/local/synchronization/lib.php');
    $admins = get_admins();
    $isadmin = false;
    foreach ($admins as $admin) {
        if ($USER->id == $admin->id) {
            $isadmin = true;
            break;
        }
    }



    if ($isadmin) {
        if (!defined('OVERRIDE_DB_CLASS')) {
            echo $OUTPUT->notification('Synchronization module doesn\'t work properly. please reinstall.', 'notifyproblem');
        }
        $listNodes = array(
            'localdatabasebackup' => array(
                'url' => new moodle_url('/local/synchronization/database_backup.php'),
                'text' => get_string('database_backup', 'local_synchronization')
            ),
            'localsynchfromserver' => array(
                'url' => new moodle_url('/local/synchronization/sync_from_server.php'),
                'text' => get_string('synchronize_from_server', 'local_synchronization')
            ),
            'localsynchronization' => array(
                'url' => new moodle_url('/local/synchronization/synchronization.php'),
                'text' => get_string('synchronize_to_server', 'local_synchronization')
            ),
            'localsynchronizationupgrade' => array(
                'url' => new moodle_url('/local/synchronization/newupgrade.php'),
                'text' => get_string('upgrade', 'local_synchronization')
            ),
        );

        $ADMIN->add('root', new admin_category('synchronization', get_string('pluginname', 'local_synchronization')), 'users');

        foreach ($listNodes as $key => $row) {
            $ADMIN->add('synchronization', new admin_externalpage($key, $row['text'], $row['url']));
        }

        $settings = new admin_settingpage('local_synchronization', get_string('setting', 'local_synchronization'));
        $ADMIN->add('synchronization', $settings);

        // load all roles in the moodle
        $systemcontext = context_system::instance();
        $allroles = role_fix_names(get_all_roles(), $systemcontext, ROLENAME_ORIGINAL);
        $rolesarray = array();
        if (!empty($allroles)) {
            foreach ($allroles as $arole) {
                $rolesarray[$arole->shortname] = ' ' . $arole->localname;
                //echo '[SHORTNAME: '. $arole->shortname.', ROLENAME: '.$arole->localname.'<br>';
            }
        }

        // adds a textfield server ip for synchronization
        $settings->add(new admin_setting_configtext('local_synchronization/serverip', get_string('serverip', 'local_synchronization'), get_string('serveripdescription', 'local_synchronization'), 'localhost'));

        // adds a textfield school id for synchronization
        $settings->add(new admin_setting_configtext('local_synchronization/schoolid', get_string('schoolid', 'local_synchronization'), get_string('schooliddescription', 'local_synchronization'), ''));

        // adds a textfield token for synchronization
        $settings->add(new admin_setting_configtext('local_synchronization/token', get_string('token', 'local_synchronization'), get_string('tokendescription', 'local_synchronization'), ''));

        $settings->add(new admin_setting_heading('local_synchronization_heading', get_string('synchronize_to_server', 'local_synchronization'), ''));

        // adds a checkbox to enable/disable synchronization
        $settings->add(new admin_setting_configcheckbox('local_synchronization/enable_to_server', get_string('enabled', 'local_synchronization'), get_string('enableddescription', 'local_synchronization'), 1));

        // adds a textfield synchronization timer on cron
        $settings->add(new admin_setting_configtext('local_synchronization/uploadtimer_to_server', get_string('uploadtimer', 'local_synchronization'), get_string('uploadtimerdescription', 'local_synchronization'), 3));
        $timer = get_config('local_synchronization', 'uploadtimer_to_server');
        if (!empty($timer)){
            $task = \core\task\manager::get_scheduled_task('\local_synchronization\task\synchronize_update');
            $task->set_hour($timer*24);
            \core\task\manager::configure_scheduled_task($task);
        }
 /*
        $settings->add(new admin_setting_heading('local_synchronization_heading2', get_string('database_backup', 'local_synchronization'), ''));

       
        // adds a checkbox to enable/disable synchronization
        $settings->add(new admin_setting_configcheckbox('local_synchronization/enable_backup', get_string('enabled', 'local_synchronization'), get_string('enableddescription_backup', 'local_synchronization'), 1));

        // adds a textfield synchronization timer on cron
        $settings->add(new admin_setting_configtext('local_synchronization/backup_timer', get_string('backuptimer', 'local_synchronization'), get_string('uploadtimerdescription', 'local_synchronization'), 3));
         */
    }
}