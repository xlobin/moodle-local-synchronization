<?php

/**
 * PLUGIN external file
 *
 * @package    local_PLUGIN
 * @copyright  20XX YOURSELF
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->libdir . "/externallib.php");

class local_synchronization_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function getcontent_parameters() {
        // FUNCTIONNAME_parameters() always return an external_function_parameters(). 
        // The external_function_parameters constructor expects an array of external_description.
        return new external_function_parameters(
                array(
            'courseid' => new external_value(PARAM_ALPHANUMEXT, 'Course Id', VALUE_DEFAULT, NULL),
            'type' => new external_value(PARAM_BOOL, 'Type', VALUE_DEFAULT, 0),
//            'token' => new external_value(PARAM_RAW, 'Type', VALUE_DEFAULT, 0),
                )
        );
    }

    /**
     * The function itself
     * @return string welcome message
     */
    public static function getcontent($courseid,  $type ) {

        global $USER, $DB;

        //Parameter validation
        //REQUIRED
//        $params = self::validate_parameters(self::getcontent_parameters(), array(
//                    'courseid' => $courseid,
//                    'type' => $type,
//                    'token' => $token
//                        )
//        );
        $courseid = $params['courseid'];
        $type = $params['type'];
        var_dump($params['token']);
        exit();

        if (!$type) {
            $listCourse = explode('_', $courseid);
            $listParam = '';
            $listId = array();
            foreach ($listCourse as $courseid) {
                $listParam .= ((strlen($listParam) > 0) ? "," : "") . ':courseid' . $courseid;
                $listId['courseid' . $courseid] = $courseid;
            }
            $courses = $DB->get_records_sql('SELECT *from {course} where category <> 0 and id not in (' . $listParam . ')', $listId);
        } else {
            $courses = array($DB->get_record('course', array('id' => $courseid)));
        }

        //Context validation
        //OPTIONAL but in most web service it should present
        $context = get_context_instance(CONTEXT_USER, $USER->id);
        self::validate_context($context);

        $result = array();

        foreach ($courses as $course) {
            $valueSql = '(';
            $columnSql = '(';
            foreach ($course as $key => $value) {
                $valueContainer = $value;
                $columnContainer = "`" . addslashes($key) . "`";
                if (!is_integer($valueContainer)) {
                    $valueContainer = "'" . addslashes($valueContainer) . "'";
                }
                $valueSql .= $valueContainer . ', ';
                $columnSql .= $columnContainer . ', ';
            }
            $valueSql = substr($valueSql, 0, count($valueSql) - 3);
            $columnSql = substr($columnSql, 0, count($columnSql) - 3);
            $valueSql .= ')';
            $columnSql .= ')';
            $sql = $columnSql . ' values ' . $valueSql;

            $result[] = array(
                'id' => $course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
                'course_summary' => $course->summary,
                'sql' => $sql
            );
        }
        //Capability checking
        //OPTIONAL but in most web service it should present
        return $result;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function getcontent_returns() {
        return new external_multiple_structure(
                new external_single_structure(
                array(
            'id' => new external_value(PARAM_INT, 'Course ID'),
            'fullname' => new external_value(PARAM_TEXT, 'Course Name'),
            'course_summary' => new external_value(PARAM_CLEANHTML, 'Course Name'),
            'shortname' => new external_value(PARAM_TEXT, 'Course Short Name'),
            'sql' => new external_value(PARAM_RAW, 'SQL'),
                )
                ), 'List of Course'
        );
    }

}
