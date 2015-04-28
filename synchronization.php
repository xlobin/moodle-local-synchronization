<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/lib/logSync.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

admin_externalpage_setup('localsynchronization');

$newSyncParam = optional_param('newsynchronization', 0, PARAM_INT);
$upload = optional_param('upload', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$spage = optional_param('spage', 0, PARAM_INT);
$ssort = optional_param('ssort', 'time', PARAM_ALPHANUMEXT);
$perpage = 20;
$baseUrl = '/local/synchronization/synchronization.php';

if (!empty($newSyncParam)) {
    $log = new logSync();
    $redirectUrl = new moodle_url($baseUrl);

    $courses = $DB->get_records_sql('SELECT {course}.* from {course} where {course}.my_id != 0');
    if ($courses) {

        /* creates a compressed zip file */

        function create_zip($files = array(), $destination = '', $overwrite = false) {
            //if the zip file already exists and overwrite is false, return false
            if (file_exists($destination) && !$overwrite) {
                return false;
            }

            //vars
            $valid_files = array();
            //if files were passed in...
            if (is_array($files)) {
                //cycle through each file
                foreach ($files as $file) {
                    //make sure the file exists
                    if (file_exists($file)) {
                        $valid_files[] = $file;
                    }
                }
            }
            //if we have good files...
            if (count($valid_files)) {
                //create the archive
                $zip = new ZipArchive();
                if ($zip->open($destination, $overwrite ? ZIPARCHIVE::OVERWRITE : ZIPARCHIVE::CREATE) !== true) {
                    return false;
                }
                //add the files
                foreach ($valid_files as $file) {
                    $zip->addFile($file, $file);
                }
                //debug
                //echo 'The zip archive contains ',$zip->numFiles,' files with a status of ',$zip->status;
                //close the zip -- done!
                $zip->close();
                //check to make sure the file exists
                return file_exists($destination);
            } else {
                return false;
            }
        }

        $files_to_zip = array();
        foreach ($courses as $course) {
            $course_id = $course->id;
            $user_doing_the_backup = 2;
            $fileName = 'course_' . $course->my_id . '.mbz';
            $downloadedFile = file_put_contents($fileName, fopen($CFG->wwwroot . "/local/synchronization/getfile.php?id=" . $course->id, 'r'));
            $bc = new backup_controller(backup::TYPE_1COURSE, $course_id, backup::FORMAT_MOODLE, backup::INTERACTIVE_NO, backup::MODE_GENERAL, $user_doing_the_backup);
            $bc->execute_plan();
            if ($downloadedFile) {
                $files_to_zip[] = $fileName;
            }
        }

        $fileLocation = __DIR__ . '/my-archive-' . date('ymdhis') . '.zip';

        function Delete($path, $parentDelete = true) {
            if (is_dir($path) === true) {
                $files = array_diff(scandir($path), array('.', '..'));
                foreach ($files as $file) {
                    Delete(realpath($path) . '/' . $file);
                }
                return (($parentDelete) ? rmdir($path) : true);
            } else if (is_file($path) === true) {
                return unlink($path);
            }
            return false;
        }

        $result = create_zip($files_to_zip, $fileLocation, true);
        if ($result) {
            $record = new stdClass();
            $record->time = date('Y-m-d H:i:s');
            $record->file_location = $fileLocation;
            $record->version = time();
            $record->status = 0;
            $lastinsertid = $DB->insert_record('ls_synchronizelog', $record, false);
            foreach ($files_to_zip as $file) {
                if (file_exists($file)) {
                    echo $file;
                    Delete($file, false);
                    exit();
                }
            }
        }
        redirect($redirectUrl, 'Successfully Created new package.', 2);
    }
    redirect($redirectUrl, 'Failed to Created new package. No Update found.', 2);
} else if (!empty($upload) && !empty($id)) {
    require_once(__DIR__ . '/lib/MyClient.php');
    $synchRecord = $DB->get_record('ls_synchronizelog', array('id' => $id));
    
//    var_dump($synchRecord);
//    exit();
    $record = new stdClass();
    $record->id = $id;
    $record->status = 1;

    $server_ip = get_config('local_synchronization', 'serverip');
    $schoolid = get_config('local_synchronization', 'schoolid');
    $token = get_config('local_synchronization', 'token');

    $clientUpload = new MyClient($server_ip, $schoolid, $token);
    $clientUpload->requestUploadSynch(array(
        'file' => $synchRecord->file_location,
        'version' => $synchRecord->version
    ));
    $responses = $clientUpload->getResponse(false);
    $redirectUrl = new moodle_url($baseUrl);

//    function updateLocal($responses) {
//        global $DB;
//        $result = true;
//        if (property_exists($responses, 'result') && count($responses->result) > 0) {
//            foreach ((array) $responses->result as $table => $item) {
//                foreach ($item as $id => $my_id) {
//                    $result = $DB->execute('update {' . $table . '} set my_id = ? where id = ?', array($my_id, $id)) && $result;
//                }
//            }
//        }
//        return $result;
//    }

    if ($responses) {
        $responses = json_decode($responses);
        if ($responses->success && $DB->update_record('ls_synchronizelog', $record, false)) {
            purge_all_caches();
            $log = new logSync();
            $log->dropDump($synchRecord->file_location);
            redirect($redirectUrl, 'Successfully synchronization new package.', 2);
        }
    }
    redirect($redirectUrl, 'Failed synchronization new package.', 2);
}

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');

echo $OUTPUT->header();
$urlNewSynchronization = new moodle_url($baseUrl, array(
    'newsynchronization' => 1,
        ));
$newSynchronization = html_writer::link($urlNewSynchronization, get_string('newpackage', 'local_synchronization'), array(
            'class' => 'btn pull-right upload_btn'
        ));
echo $OUTPUT->heading(get_string('synchronize_to_server', 'local_synchronization') . $newSynchronization);

if (!$CFG->enablewebservices) {
    echo $OUTPUT->notification(get_string('turnonwebservices', 'local_synchronization'), 'notifyproblem');
}

$table = new flexible_table('tbl_synchronizelog');

$table->define_columns(array('time', 'version', 'action'));
$table->define_headers(array('Time', 'Version', get_string('action', 'local_synchronization')));
$table->no_sorting('action');
$table->set_control_variables(array(
    TABLE_VAR_SORT => 'ssort',
    TABLE_VAR_IFIRST => 'sifirst',
    TABLE_VAR_ILAST => 'silast',
    TABLE_VAR_PAGE => 'spage'
));
$table->define_baseurl($baseUrl);
$table->set_attribute('class', 'admintable blockstable generaltable');
$table->set_attribute('id', 'ls_synchronizelog_table');

$jumlahLog = $DB->count_records('ls_synchronizelog');
$table->pagesize($perpage, $jumlahLog);
$table->sortable(true, 'time', SORT_DESC);
$table->set_attribute('cellspacing', '0');
$table->setup();
$sort = $table->get_sql_sort();
$synchLog = $DB->get_records('ls_synchronizelog', array(), $sort, '*', ($spage * $perpage), $perpage);
$urlUploadBackup = new moodle_url($baseUrl, array(
    'upload' => 1,
        ));
foreach ($synchLog as $key => $value) {
    $action = ($value->status == 0) ? html_writer::link($urlUploadBackup . '&id=' . $value->id, get_string('synch', 'local_synchronization'), array(
                'class' => 'btn upload_btn',
            )) : '';
    $table->add_data(array(
        $value->time,
        $value->version,
        $action
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
