<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/lib/MyClient.php');
require_once(__DIR__ . '/lib/MySynchronization.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

admin_externalpage_setup('localsynchfromserver');

$spage = optional_param('spage', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$download = optional_param('download', 0, PARAM_INT);
$version = optional_param('version', 0, PARAM_INT);
$downloadall = optional_param('downloadall', 0, PARAM_INT);
$status = optional_param('status', 0, PARAM_ALPHA);
$ssort = optional_param('ssort', 'time', PARAM_ALPHANUMEXT);
$perpage = 20;
$baseUrl = '/local/synchronization/sync_from_server.php';

$server_ip = get_config('local_synchronization', 'serverip');
$schoolid = get_config('local_synchronization', 'schoolid');
$token = get_config('local_synchronization', 'token');

$ress = new MyClient($server_ip, $schoolid, $token);

if (!empty($courseid) && !empty($download)) {
    $path = $CFG->dataroot . '/temp/backup/';
    $tempFile = $path . 'backup.mbz';
    if (!file_exists($path)) {
        mkdir($path);
    }
    $downloadedFile = file_put_contents($tempFile, fopen('http://' . $server_ip . "/local/schoolreg/getfile.php?id=" . $courseid, 'r'));
    if ($downloadedFile) {
        $zip = new ZipArchive();
        if ($zip->open($tempFile) === TRUE) {

            function Delete($path, $parentDelete = true) {
                if (is_dir($path) === true) {
                    $files = array_diff(scandir($path), array('.', '..'));
                    foreach ($files as $file) {
                        Delete(realpath($path) . '/' . $file);
                    }
                    return (($parentDelete) ? rmdir($path) : true);
                } else if (is_file($path) === true) {
                    return true;
                }
                return false;
            }

            Delete($path . '1234567890/', false);
            $zip->extractTo($path . DIRECTORY_SEPARATOR . '1234567890');
            $zip->close();

            $transaction = $DB->start_delegated_transaction();
            $folder = '1234567890';

            $categoryid = 1; // e.g. 1 == Miscellaneous
            $userdoingrestore = 2; // e.g. 2 == admin
            $before = $DB->get_record('course', array('my_id' => $courseid));

            if ($before) {
                $course_id = $before->id;
                $controller = new restore_controller($folder, $course_id, backup::INTERACTIVE_NO, backup::MODE_GENERAL, $userdoingrestore, backup::TARGET_EXISTING_DELETING);
                $controller->execute_precheck();
                $controller->execute_plan();
            } else {
                $course_id = restore_dbops::create_new_course('', '', $categoryid);
                $controller = new restore_controller($folder, $course_id, backup::INTERACTIVE_NO, backup::MODE_GENERAL, $userdoingrestore, backup::TARGET_NEW_COURSE);
                $controller->execute_precheck();
                $controller->execute_plan();
            }

            $update = $DB->get_record('course', array('id' => $course_id));
            $update->my_id = $courseid;
            $update->sync_version = $version;
            $DB->update_record('course', $update);

            $transaction->allow_commit();
            Delete($path . '1234567890/', false);
            purge_all_caches();
            redirect(new moodle_url($baseUrl), 'Successfully ' . get_string('download', 'local_synchronization') . ' Course Content.', 2);
        }
    }
    redirect(new moodle_url($baseUrl), 'Failed ' . get_string('download', 'local_synchronization') . ' Course Content.', 2);
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

$courses = $DB->get_records('course', array(), '', 'my_id, sync_version, category');
$course_id = '';
foreach ($courses as $course) {
    if ($course->category != '0')
        $course_id .= ((strlen($course_id) > 0) ? "_" : "") . $course->my_id . '-' . $course->sync_version;
}

$ress->requesting(array('courseid' => $course_id));
$responses = $ress->getResponse(false);
$result = json_decode($responses);

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

$table->sortable(true, 'fullname', SORT_DESC);
$table->no_sorting('action');
$table->set_attribute('cellspacing', '0');
$table->setup();
$sort = $table->get_sql_sort();
$urlDownload = new moodle_url($baseUrl, array('download' => 1));
$no = 1;
if ($result) {
    if (is_object($result) && property_exists($result, 'error')) {}else{
        foreach ($result as $key => $value) {
            $action = html_writer::link($urlDownload . '&courseid=' . $value->id . '&version=' . $value->version, get_string('download', 'local_synchronization'), array(
                        'class' => 'btn upload_btn',
            ));

            $value->course_summary = (isset($value->course_summary)) ? $value->course_summary : '';
            $table->add_data(array(
                $no++,
                $value->fullname,
                $value->shortname,
                $value->course_summary,
                $action,
            ));
        }
    }
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
