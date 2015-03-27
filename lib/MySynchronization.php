<?php

require_once(__DIR__ . '/course.php');
require_once(__DIR__ . '/moodle_relational_table.php');

/**
 * Execute Synchronization
 *
 * @author Muhammad Bahjah
 */
class MySynchronization {

    public $response;
    public $status;
    public $courseid;
    public $files = array();
    public $attributes = array();
    private $_moodleRelation;
    public $DB;

    public function __construct($params) {
        global $DB;
        $this->DB = $DB;
        $this->_moodleRelation = new moodle_relational_table();
        $this->response = $params['response'];
        $this->status = $params['status'];
        $this->courseid = $params['courseid'];
        $this->parseResponse();
    }

    /**
     * parse xml Response
     */
    public function parseResponse() {
        global $OUTPUT;
        $responses = $this->response;
        if (property_exists($responses->MULTIPLE, 'SINGLE')) {
            $listAcceptedAttributes = array(
                'query',
//                'category',
                'version',
                'course_params_data',
                'course_params_overview'
            );
            foreach ($responses->MULTIPLE[0]->SINGLE as $key => $response) {
                $attributes = array();
                $files = array();
                foreach ($response->KEY as $key2 => $value2) {
                    foreach ($value2->attributes() as $key3 => $value3) {
                        $string = (string) $value3;
                        if (in_array($string, $listAcceptedAttributes)) {
                            $attributes[$string] = (string) $value2->VALUE;
                        }
                        if ($string == 'files') {
                            $file = (string) $value2->VALUE;
                            if ($file) {
                                $files[] = $file;
                            }
                        }
                    }
                }
            }
        } else if (property_exists($responses, 'ERRORCODE')) {
            echo $OUTPUT->notification($responses->MESSAGE . "<br/>" . $responses->DEBUGINFO, 'notifyproblem');
        }

        if (isset($attributes)) {
            $this->attributes = $attributes;
        }
        if (isset($files)) {
            $this->files = $files;
        }
    }

    /**
     * get Item of object
     * @param type array object
     * @return type
     */
    public function getChild($child, $parent = false) {
        $success = true;
        foreach ($child as $key => $value) {
            foreach ($value as $item) {
                $itemChild = false;
                if (property_exists($item, 'my_item')) {
                    $itemChild = (array) $item->my_item;
                    unset($item->my_item);
                }

                if ($parent) {
                    $this->_moodleRelation->setTable($parent, array(
                        'tableName' => $key,
                        'tableData' => $item
                    ));
                    $item = $this->_moodleRelation->fixRelation();
                }

                $item = $this->executeQuery($item, $key);
                $this->_moodleRelation->create_context_table($key, $item);

                $success = $success && $item;
                if ($itemChild && $success) {
                    $success = $success && $this->getChild($itemChild, array(
                                'tableName' => $key,
                                'tableData' => $item
                    ));
                    $item = $this->_moodleRelation->updateRelation($key, $item);
                    if ($item) {
                        $this->DB->update_record($key, $item);
                    }
                }
            }
        }

        return $success;
    }

    /**
     * executing query
     * @global type $DB
     * @param object $query
     * @param string $table
     * @return boolean
     */
    public function executeQuery($query, $table = '') {

        $listContext = array(
            'course_categories' => CONTEXT_COURSECAT,
            'course_modules' => CONTEXT_MODULE,
        );

        if (!empty($table)) {
            $query->my_id = $query->id; // assign server id into client my_id
            $jumlah = $this->DB->count_records($table, array('my_id' => $query->id));
            if ($jumlah > 0) {
                $record = $this->DB->get_record($table, array('my_id' => $query->id));
                $query->id = $record->id;

                return ($this->DB->update_record($table, $query)) ? $query : false;
            } else {
                unset($query->id);
                $query->id = $this->DB->insert_record($table, $query);
                return ($query) ? $query : false;
            }
        }

        return false;
    }

    /**
     * executing synchronization
     * @return boolean
     */
    public function execute() {
        $status = $this->status;
        $attributes = $this->attributes;
        $files = $this->files;

        if ($files) {
            $files = (array) json_decode($files[0]);
        }

        $query = array();
        if (isset($attributes['query'])) {
            $query = (array) json_decode($attributes['query']);
            unset($attributes['query']);

            if (isset($query['course_categories'])) {
                $category = $query['course_categories'];
                unset($query['course_categories']);
            }
        }

        $course_params_data = array();
        if (isset($attributes['course_params_data'])) {
            $course_params_data = json_decode($attributes['course_params_data']);
            unset($attributes['course_params_data']);
        }
        $course_params_overview = array();
        if (isset($attributes['course_params_overview'])) {
            $course_params_overview = json_decode($attributes['course_params_overview']);
            unset($attributes['course_params_overview']);
        }

        if (isset($attributes['version'])) {
            $version = $attributes['version'];
            unset($attributes['version']);
        }

        if ($status == 'd') {
            return delete_course($this->courseid, false);
        } else if ($status == 'u') {
            try {
                $transaction = $this->DB->start_delegated_transaction();
                $jumlah = $this->DB->count_records('course', array('id' => $this->courseid));
                $success = ($jumlah > 0) ? delete_course($this->courseid, false) : true;

                if (isset($category)) {
                    $this->getChild(array('course_categories' => $category));
                }

                foreach ($attributes as $table => $insert) {
                    if (!$this->DB->execute($insert)) {
                        $success = $success && false;
                    }
                }

                $course = $course_params_data;
                $course->summary_editor = (array) $course->summary_editor;
                $coursecontext = context_course::instance($course->id);
                $overview = (array) $course_params_overview;
                $overview['context'] = $coursecontext;
                if (isset($version)) {
                    $course->sync_version = $version;
                }
                $success = $success && update_course($course, $overview);

                if (isset($query)) {
                    $this->getChild($query);
                }

                if (isset($files)) {
                    $fs = get_file_storage();
                    foreach ($files as $file) {
                        $file = (object) $file;
                        $modules_id = '';
                        if (property_exists($file, 'my_url')) {
                            $modules_id = $file->modules_id;
                            $my_url = $file->my_url;
                            unset($file->my_url);
                        }
                        unset($file->modules_id);
                        if (!empty($modules_id)) {
                            $record = $this->DB->get_record('course_modules', array('my_id' => $modules_id));
                            $context = context_module::instance($record->id);
                            $file->contextid = $context->id;
                            $fs->create_file_from_url($file, $my_url);
                        }
                    }
                }

                $transaction->allow_commit();
            } catch (Exception $exc) {
                $transaction->rollback($exc);
            }

            return $success;
        } else if ($status == 'c') {
            try {
                $transaction = $this->DB->start_delegated_transaction();
                $jumlah = $this->DB->count_records('course', array('id' => $this->courseid));
                $success = ($jumlah > 0) ? delete_course($this->courseid, false) : true;

                if (isset($category)) {
                    $this->getChild(array('course_categories' => $category));
                }

                $attributes['alteration'] = "ALTER TABLE {course} AUTO_INCREMENT=" . ($this->courseid);
                foreach ($attributes as $table => $insert) {
                    if (!$this->DB->execute($insert)) {
                        $success = $success && false;
                    }
                }
                $course = $course_params_data;

                $course->summary_editor = (array) $course->summary_editor;
                $course->id = $this->courseid;

                if (isset($version)) {
                    $course->sync_version = $version;
                }
                $catcontext = context_coursecat::instance($course->category);
                $overview = (array) $course_params_overview;
                $overview['context'] = $catcontext;
                $course = create_my_course($course, $overview);
                $this->DB->delete_records("course_sections", array("course" => $this->courseid));

                if (isset($query)) {
                    $this->getChild($query);
                }

                if (isset($files)) {
                    $fs = get_file_storage();
                    foreach ($files as $file) {
                        $file = (object) $file;
                        $modules_id = '';
                        if (property_exists($file, 'my_url')) {
                            $modules_id = $file->modules_id;
                            $my_url = $file->my_url;
                            unset($file->my_url);
                        }
                        unset($file->modules_id);
                        if (!empty($modules_id)) {
                            $record = $this->DB->get_record('course_modules', array('my_id' => $modules_id));
                            $context = context_module::instance($record->id);
                            $file->contextid = $context->id;
                            $fs->create_file_from_url($file, $my_url);
                        }
                    }
                }
                $transaction->allow_commit();
            } catch (Exception $exc) {
                $transaction->rollback($exc);
            }
            return $success;
        }
        return false;
    }

}
