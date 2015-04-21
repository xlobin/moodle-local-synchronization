<?php

require_once(__DIR__ . '/course.php');
require_once(__DIR__ . '/moodle_relational_table.php');
require_once(__DIR__ . '/moodlelib.php');
require_once($CFG->dirroot . '/course/lib.php');

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
    private $_existingData;
    private $_listId;
    public $DB;

    public function __construct($params) {
        global $DB;
        $this->DB = $DB;
        $this->_moodleRelation = new moodle_relational_table();
        $this->response = $params['response'];
        $this->status = $params['status'];
        $this->courseid = $params['courseid'];
        $this->getExistingData();
        $this->parseResponse();
    }

    public function getExistingData() {
        $sections = $this->DB->get_records('course_sections', array('course' => $this->courseid));
        $this->_existingData['course_sections'] = $sections;
        foreach ($sections as $keySection => $section) {
            if ($section->sequence != "" || $section->sequence) {
                $modlist = explode(',', $section->sequence);
                $modules = array();
                foreach ($modlist as $mod) {

                    $module = $this->DB->get_record('course_modules', array('id' => $mod));
                    if ($module) {
                        $mod_type = $this->DB->get_record('modules', array('id' => $module->module));
                        $module_item = $this->DB->get_records($mod_type->name, array('id' => $module->instance));
                        foreach ($module_item as $keyItem => $item) {
                            $functionName = 'getMy' . ucfirst(strtolower($mod_type->name));
                            if (function_exists($functionName)) {
                                if ($moduleItem = $functionName($item)) {
                                    if (!isset($this->_existingData[$keyItem])) {
                                        $this->_existingData[$mod_type->name] = array();
                                    }
                                    $this->_existingData[$mod_type->name] = $this->_existingData[$mod_type->name] + $moduleItem;
                                }
                            }
                        }
                        $modules[$module->id] = $module;
                    }
                }
                if (!isset($this->_existingData['course_modules'])) {
                    $this->_existingData['course_modules'] = array();
                }
                $this->_existingData['course_modules'] = $this->_existingData['course_modules'] + $modules;
            }
            $sections[$keySection] = $section;
        }
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
                if ($key == 'course_categories') {
                    $item->parent = 0;
                }
                $item = $this->executeQuery($item, $key);

                if (isset($parent['tableName']) && $parent['tableName'] == 'course_modules' && $item) {
                    $update = $parent['tableData'];
                    $update->instance = $item->id;
                    $this->DB->update_record($parent['tableName'], $update);
                }
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
        if (!empty($table)) {
            $query->my_id = $query->id;
            $jumlah = $this->DB->count_records($table, array('my_id' => $query->id));
            if ($jumlah > 0) {
                $record = $this->DB->get_record($table, array('my_id' => $query->id));
                $query->id = $record->id;
                if ($table == 'course_categories') {
                    $query->depth = $record->depth;
                    $query->path = $record->path;
                }
                if (isset($this->_existingData[$table])) {
                    unset($this->_existingData[$table][$query->id]);
                }
                return ($this->DB->update_record($table, $query)) ? $query : false;
            } else {
                unset($query->id);
                $query->id = $this->DB->insert_record($table, $query);
                if (isset($this->_listId[$table])) {
                    unset($this->_existingData[$table][$query->id]);
                }
                return ($query) ? $query : false;
            }
        }

        return false;
    }

    public function deleteRemovedData() {
        foreach ($this->_existingData as $table => $value) {
            if ($value) {
                foreach ($value as $key => $item) {
                    if (!empty($table) && property_exists($item, 'id')) {
                        $this->DB->delete_records($table, array('id' => $item->id));
                    }
                }
            }
        }
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
                $success = true;
                $course = $course_params_data;
                if (isset($category)) {
                    $this->getChild(array('course_categories' => $category));
                    foreach ($category as $keyCategory => $valueCategory) {
                        $course->category = $valueCategory->id;
                    }
                }

                foreach ($attributes as $table => $insert) {
                    if (!$this->DB->execute($insert)) {
                        $success = $success && false;
                    }
                }

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
                        var_dump($file);
                        $modules_id = '';
                        if (property_exists($file, 'my_url')) {
                            if (property_exists($file, 'modules_id') && !empty($file->modules_id)) {
                                $modules_id = $file->modules_id;
                                unset($file->modules_id);
                            }
                            $my_url = $file->my_url;
                            unset($file->my_url);
                        }
                        if (isset($modules_id)) {
                            $record = $this->DB->get_record('course_modules', array('my_id' => $modules_id));
                            if ($record) {
                                $context = context_module::instance($record->id);
                                $file->contextid = $context->id;
                                $fs->create_file_from_url($file, $my_url);
                            }
                        } else {
                            $context = context_course::instance($course->id);
                            $file->contextid = $context->id;
                            $fs->create_file_from_url($file, $my_url);
                        }
                    }
                }
                $this->deleteRemovedData();
                $transaction->allow_commit();
            } catch (Exception $exc) {
                $transaction->rollback($exc);
            }

            return true;
        } else if ($status == 'c') {
            try {
                $transaction = $this->DB->start_delegated_transaction();
                $jumlah = $this->DB->count_records('course', array('id' => $this->courseid));
                $success = ($jumlah > 0) ? delete_course($this->courseid, false) : true;
                $course = $course_params_data;

                if (isset($category)) {
                    $this->getChild(array('course_categories' => $category));
                    foreach ($category as $keyCategory => $valueCategory) {
                        $course->category = $valueCategory->id;
                    }
                }

                $attributes['alteration'] = "ALTER TABLE {course} AUTO_INCREMENT=" . ($this->courseid);
                foreach ($attributes as $table => $insert) {
                    if (!$this->DB->execute($insert)) {
                        $success = $success && false;
                    }
                }

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
                            if (property_exists($file, 'modules_id') && !empty($file->modules_id)) {
                                $modules_id = $file->modules_id;
                                unset($file->modules_id);
                            }
                            $my_url = $file->my_url;
                            unset($file->my_url);
                        }
                        if (isset($modules_id)) {
                            $record = $this->DB->get_record('course_modules', array('my_id' => $modules_id));
                            if ($record) {
                                $context = context_module::instance($record->id);
                                $file->contextid = $context->id;
                                $fs->create_file_from_url($file, $my_url);
                            }
                        } else {
                            $context = context_course::instance($course->id);
                            $file->contextid = $context->id;
                            $fs->create_file_from_url($file, $my_url);
                        }
                    }
                }

                $this->deleteRemovedData();
                $transaction->allow_commit();
            } catch (Exception $exc) {
                $transaction->rollback($exc);
            }
            return $success;
        }
        return false;
    }

}
