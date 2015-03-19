<?php

require_once(__DIR__ . '/course.php');

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

    public function __construct($params) {
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
                'category',
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

    public function getChild($child) {
        $success = true;
        foreach ($child as $key => $value) {
            foreach ($value as $item) {
                $itemChild = false;
                if (property_exists($item, 'my_item')) {
                    $itemChild = (array) $item->my_item;
                    unset($item->my_item);
                }
                $success = $success && $this->executeQuery($item, $key);
                if ($itemChild) {
                    $success = $success && $this->getChild($itemChild);
                }
            }
        }
        
        return $success;
    }

    public function executeQuery($query, $table = '') {
        global $DB;
                
        $listContext = array(
            'course_categories' => CONTEXT_COURSECAT,
            'course_modules' => CONTEXT_MODULE,
        );

        if (!empty($table)) {
            $query->my_id = $query->id; // assign server id into client my_id
            $jumlah = $DB->count_records($table, array('my_id' => $query->id));
            if ($jumlah > 0 && $jumlahParent > 0) {
                $record = $DB->get_record($table, array('my_id' => $query->id));
                $query->id = $record->id;
                
                return $DB->update_record($table, $query);
            } else {
                unset($query->id);
                
                return $DB->insert_record($table, $query);
            }
        }
        
        return true;
    }

    /**
     * executing synchronization
     * @return boolean
     */
    public function execute() {
        $status = $this->status;
        $attributes = $this->attributes;

        $query = array();
        if (isset($attributes['query'])) {
            $query = (array) json_decode($attributes['query']);
            unset($attributes['query']);
        }
        if (isset($query)) {
            $this->getChild($query);
            exit();
        }

        if (isset($attributes['category'])) {
            if (empty($attributes['category'])) {
                unset($attributes['course_categories']);
                unset($attributes['category']);
            } else {
                $jumlah = $DB->count_records('course_categories', array('id' => $attributes['category']));
                if ($jumlah > 0) {
                    $DB->delete_records('course_categories', array('id' => $attributes['category']));
                    unset($attributes['category']);
                } else {
                    unset($attributes['category']);
                }
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
            foreach ($attributes as $table => $insert) {
                if (!$DB->execute($insert)) {
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

            if (isset($files)) {
                foreach ($files as $file) {
                    $file = json_decode($file);
                    $context = context_course::instance($course->id, MUST_EXIST);
                    $file->contextid = $context->id;
                    $url = $file->url;
                    unset($file->url);
                    $fs = get_file_storage();
                    $fs->create_file_from_url($file, $url);
                }
            }



            return $success;
        } else if ($status == 'c') {
            $success = delete_course($this->courseid, false);

            $attributes['alteration'] = "ALTER TABLE {course} AUTO_INCREMENT=" . ($courseid);
            foreach ($attributes as $table => $insert) {
                if (!$DB->execute($insert)) {
                    $success = $success && false;
                }
            }
            $course = $course_params_data[0];

            $course->summary_editor = (array) $course->summary_editor;
            $course->id = $courseid;

            if (isset($version)) {
                $course->sync_version = $version;
            }
            $catcontext = context_coursecat::instance($course->category);
            $overview = (array) $course_params_overview[0];
            $overview['context'] = $catcontext;
            $course = create_my_course($course, $overview);

            if (isset($files)) {
                foreach ($files as $file) {
                    $file = json_decode($file);
                    $context = context_course::instance($course->id, MUST_EXIST);
                    $file->contextid = $context->id;
                    $url = $file->url;
                    unset($file->url);
                    $fs = get_file_storage();
                    $fs->create_file_from_url($file, $url);
                }
            }

            return $success;
        }
        return false;
    }

}
