<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/lib/MyClient.php');
require_once($CFG->libdir.'/filelib.php');

admin_externalpage_setup('localsynchfromserver');

$spage = optional_param('spage', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$download = optional_param('download', 0, PARAM_INT);
$downloadall = optional_param('downloadall', 0, PARAM_INT);
$status = optional_param('status', 0, PARAM_ALPHA);
$ssort = optional_param('ssort', 'time', PARAM_ALPHANUMEXT);
$perpage = 20;
$baseUrl = '/local/synchronization/sync_from_server.php';

$server_ip = get_config('local_synchronization', 'serverip');
$schoolid = get_config('local_synchronization', 'schoolid');
$token = get_config('local_synchronization', 'token');

$ress = new MyClient($server_ip, $schoolid, $token);
$message = array(
    'u' => 'upgrade',
    'd' => 'delete',
    'c' => 'download'
);
if (!empty($courseid) && !empty($download)) {
    $ress->request(array('courseid' => $courseid, 'type' => true));
    $responses = $ress->getResponse();

    if ($responses) {
        if (property_exists($responses->MULTIPLE, 'SINGLE')) {
            foreach ($responses->MULTIPLE[0]->SINGLE as $key => $response) {
                $attributes = array();
                $files = array();
                foreach ($response->KEY as $key2 => $value2) {
                    foreach ($value2->attributes() as $key3 => $value3) {
                        $string = (string) $value3;
                        if ($string == 'course' || $string == 'course_categories' || $string == 'category' || $string == 'query') {
                            $attributes[$string] = (string) $value2->VALUE;
                        }
                        if ($string == 'files') {
                            $files[] = (string) $value2->VALUE;
                        }
                    }
                }
                $result[] = $attributes;
            }
        } else if (property_exists($responses, 'ERRORCODE')) {
            echo $OUTPUT->notification($responses->MESSAGE . "<br/>" . $responses->DEBUGINFO, 'notifyproblem');
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
    }
    $success = true;
    if (isset($attributes)) {
        if (isset($attributes['query'])) {
            $query = json_decode($query);
            foreach ($query as $row) {
                if (!$DB->execute($row)) {
                    $success = $success && false;
                }
            }
        } else {
            if ($status == 'd') {
                ob_start();
                delete_course($courseid);
                ob_end_clean();
            } else if ($status == 'u') {
                $DB->delete_records('course', array('id' => $courseid));
                foreach ($attributes as $table => $insert) {
                    if (!$DB->execute($insert)) {
                        $success = $success && false;
                    }
                }

                if (isset($files)) {
                    foreach ($files as $file) {
                        $file = json_decode($file);
                        $url = $file->url;
                        unset($file->url);
                        $fs = get_file_storage();
                        $fs->create_file_from_url($file, $url);
                    }
                }
            } else if ($status == 'c') {
                foreach ($attributes as $table => $insert) {
                    if (!$DB->execute($insert)) {
                        $success = $success && false;
                    }
                }

                if (isset($files)) {
                    foreach ($files as $file) {
                        $file = json_decode($file);
                        $url = $file->url;
                        unset($file->url);
                        $fs = get_file_storage();
                        $fs->create_file_from_url($file, $url);
                    }
                }
            }
        }

        if ($success) {
            redirect(new moodle_url($baseUrl), 'Successfully ' . get_string($message[$status], 'local_synchronization') . ' Course Content.', 2);
            core_plugin_manager::reset_caches();
        }
    }
    redirect(new moodle_url($baseUrl), 'Failed ' . get_string($message[$status], 'local_synchronization') . ' Course Content.', 2);
}

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
echo $OUTPUT->header();
$urlNewBackup = new moodle_url($baseUrl, array(
    'downloadall' => true,
        ));
$downloadAll = html_writer::link($urlNewBackup, get_string('downloadall', 'local_synchronization'), array(
            'class' => 'btn pull-right'
        ));
echo $OUTPUT->heading(get_string('synchronize_from_server', 'local_synchronization'));

if (!$CFG->enablewebservices) {
    echo $OUTPUT->notification(get_string('turnonwebservices', 'local_synchronization'), 'notifyproblem');
}

$courses = $DB->get_records('course', array(), '', 'id, sync_version, category');
$course_id = '';
foreach ($courses as $course) {
    if ($course->category != '0')
        $course_id .= ((strlen($course_id) > 0) ? "_" : "") . $course->id . '-' . $course->sync_version;
}

$ress->request(array('courseid' => $course_id));
$responses = $ress->getResponse();

$result = array();
$listId = array();
$listParam = '';
if ($responses) {
    if (property_exists($responses->MULTIPLE, 'SINGLE')) {
        foreach ($responses->MULTIPLE[0]->SINGLE as $key => $response) {
            $attributes = array();
            foreach ($response->KEY as $key2 => $value2) {
                foreach ($value2->attributes() as $key3 => $value3) {
                    $attributes[(string) $value3] = (string) $value2->VALUE;
                    if ((string) $value3 === 'id') {
                        $listId['courseid' . $value2->VALUE] = (string) $value2->VALUE;
                        $listParam .= ((strlen($listParam) > 0) ? ',' : '') . ':courseid' . $value2->VALUE;
                    }
                }
            }
            $result[] = $attributes;
        }
    } else if (property_exists($responses, 'ERRORCODE')) {
        echo $OUTPUT->notification($responses->MESSAGE . "<br/>" . $responses->DEBUGINFO, 'notifyproblem');
    }
}

$table = new flexible_table('tbl_synchronize_from_server');

$table->define_columns(array('id', 'fullname', 'shortname', 'summary', 'action'));
$table->define_headers(array(get_string('id', 'local_synchronization'), get_string('course_name', 'local_synchronization'),
    get_string('shortname', 'local_synchronization'),
    get_string('summary', 'local_synchronization'),
    get_string('action', 'local_synchronization')));
$table->set_control_variables(array(
    TABLE_VAR_SORT => 'ssort',
    TABLE_VAR_IFIRST => 'sifirst',
    TABLE_VAR_ILAST => 'silast',
    TABLE_VAR_PAGE => 'spage'
));
$table->define_baseurl($baseUrl);
$table->set_attribute('class', 'admintable blockstable generaltable');
$table->set_attribute('id', 'ls_synchronize_from_server_table');


//$table->pagesize($perpage, $jumlahBackupLog);
$table->sortable(true, 'fullname', SORT_DESC);
$table->no_sorting('action');
$table->set_attribute('cellspacing', '0');
$table->setup();
$sort = $table->get_sql_sort();
//$backupLog = $DB->get_records('ls_backupdatabaselog', array(), $sort, '*', ($spage * $perpage), $perpage);
$urlDownload = new moodle_url($baseUrl, array(
    'download' => 1,
        ));
foreach ($result as $key => $value) {
    if ($value['status'] == 'c') {
        
    } else if ($value['status'] == 'd') {
        $action = html_writer::link($urlDownload . '&courseid=' . $value['id'] . '&status=' . $value['status'], get_string('download', 'local_synchronization'), array(
                    'class' => 'btn',
        ));
    } else if ($value['status'] == 'u') {
        $action = html_writer::link($urlDownload . '&courseid=' . $value['id'] . '&status=' . $value['status'], get_string('download', 'local_synchronization'), array(
                    'class' => 'btn',
        ));
    }

    switch ($value['status']) {
        case 'u':
            $message = $message['u'];
            break;
        case 'd':
            $message = $message['d'];
            $value = (array) $DB->get_record('course', array('id' => $value['id']));
            $value['status'] = 'd';
            break;
        default:
            $message = $message['c'];
            break;
    }

    $action = html_writer::link($urlDownload . '&courseid=' . $value['id'] . '&status=' . $value['status'], get_string($message, 'local_synchronization'), array(
                'class' => 'btn upload_btn',
    ));

    $value['course_summary'] = (isset($value['course_summary'])) ? $value['course_summary'] : '';
    $table->add_data(array(
        $value['id'],
        $value['fullname'],
        $value['shortname'],
        $value['course_summary'],
        $action,
    ));
}
$table->print_html();
?>
<div id="progress_bar" style="display: none;" title="Loading...">
    <img src="asset/ajax-loader-long.gif"/>
</div>
<script type="text/javascript">
    $(document).ready(function() {
        $(".upload_btn").each(function() {
            $(this).click(function() {
                $("#progress_bar").dialog('open');
            });
        });

        $("#progress_bar").dialog({
            autoOpen: false,
            width: 300,
            height: 90,
            modal: true,
            draggable: false,
            closeOnEscape: false,
            closeText: "hideProgressBar",
            resizable: false,
            open: function() {
                $("button[title='hideProgressBar']").hide();
            },
            close: function() {
                $("button[title='hideProgressBar']").show();
            }
        });
    });
</script>
<?php
echo $OUTPUT->footer();
