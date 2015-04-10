<?php
require_once(__DIR__ . '/course.php');
require_once($CFG->libdir . '/accesslib.php');
/*
 * Database MySQLDump Class File
 * Copyright (c) 2015 by Muhammad Bahjah modification https://code.google.com/p/db-mysqldump/
 * Muhammad Bahjah
 * GNU General Public License v3 http://www.gnu.org/licenses/gpl.html
 */

class logSync {

    private $_pathFile = '';

    /**
     * 
     * @param boolean $asFile
     * @param string $fileName
     * @param string $path
     */
    function generate_dump($asFile = false, $fileName = '', $path = '') {
        global $CFG, $DB;

        $courses = $DB->get_records_sql('select * from {course} where category != 0');
        $rolesAccepted = array(3, 4, 5);
        foreach ($courses as $keyCourse => $course) {
            $coursecontext = context_course::instance($course->id);
            $sections = $DB->get_records('course_sections', array('course' => $course->id));
            foreach ($sections as $keySection => $section) {
                if ($section->sequence != "" || $section->sequence) {
                    $modlist = explode(',', $section->sequence);
                    $modules = array();
                    foreach ($modlist as $mod) {
                        $module = $DB->get_record('course_modules', array('id' => $mod));
                        $mod_type = $DB->get_record('modules', array('id' => $module->module));
                        $module_item = $DB->get_records($mod_type->name, array('id' => $module->instance));
                        foreach ($module_item as $keyItem => $item) {
                            $functionName = 'getMy' . ucfirst(strtolower($mod_type->name));
                            if (function_exists($functionName)) {
                                if ($moduleItem = $functionName($item))
                                    $module_item[$keyItem]->my_item = $moduleItem;
                            }
                        }
                        $module->my_item[$mod_type->name] = $module_item;
                        $modules[$module->id] = $module;
                    }
                    $section->my_item = array('course_modules' => $modules);
                }
                $sections[$keySection] = $section;
            }
            $role_assignments = $DB->get_records('role_assignments', array('contextid' => $coursecontext->id));
            foreach ($role_assignments as $keyRolesAssignment => $role_assignment) {
                if (!in_array($role_assignment->roleid, $rolesAccepted)) {
                    unset($role_assignments[$keyRolesAssignment]);
                    continue;
                }
                $users = $DB->get_records('user', array('id' => $role_assignment->userid));
                foreach ($users as $keyUser => $user) {
                    $user_preferences = $DB->get_records('user_preferences', array('userid' => $user->id));
                    $user_enrolments = $DB->get_records('user_enrolments', array('userid' => $user->id));
                    $users[$keyUser]->my_item = array(
                        'user_enrolments' => $user_enrolments,
                        'user_preferences' => $user_preferences,
                    );
                }
                $role_assignments[$keyRolesAssignment]->my_item = array(
                    'user' => $users
                );
            }
            $courses[$keyCourse]->my_item = array(
                'course_sections' => $sections,
                'role_assignments' => $role_assignments
            );
        }
        $courses = array(
            'course' => $courses
        );
        $contents = json_encode($courses);
        if ($asFile) {
            $fileName = (empty($fileName)) ? 'sync_' . date('ymdhis') . '.json' : $fileName;
            $pathFolder = $CFG->dirroot . '/synch';
            $pathFile = $pathFolder . '/' . $fileName;
            if (!file_exists($pathFolder)) {
                mkdir($pathFolder, 0700);
            }

            $fileSize = file_put_contents($pathFile, $contents);
            $this->_pathFile = $pathFile;
            return (($fileSize) ? $pathFile : "");
        } else {
            echo $contents;
        }
    }

    /**
     * drop file of sync.json
     * @param string $path
     */
    function dropDump($path = '') {
        $filePath = (!empty($path)) ? $path : $this->_pathFile;
        if (file_exists($filePath) && $filePath != '' && stripos($filePath, '.json')) {
            unlink($filePath);
        }
    }

}

?>