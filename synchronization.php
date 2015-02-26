<?php

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
 require_once(__DIR__ . '/lib/logSync.php');

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
    if ($path = $log->generate_dump(true)) {
        if (!empty($path)) {
            $record = new stdClass();
            $record->time = date('Y-m-d H:i:s');
            $record->file_location = $path;
            $record->version = time();
            $record->status = 0;
            $lastinsertid = $DB->insert_record('ls_synchronizelog', $record, false);
            redirect($redirectUrl, 'Successfully Created new package.', 2);
        }
    }
    redirect($redirectUrl, 'Failed to Created new package. No Update found.', 2);
} else if (!empty($upload) && !empty($id)) {
    require_once(__DIR__ . '/lib/MyClient.php');
    $synchRecord = $DB->get_record('ls_synchronizelog', array('id' => $id));
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
    if ($responses) {
        $responses = json_decode($responses);
        if ($responses->success && $DB->update_record('ls_synchronizelog', $record, false)) {
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
        $(document).ready(function(){
            $(".upload_btn").each(function(){
                $(this).click(function(){
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
                open: function(){
                    $("button[title='hideProgressBar']").hide();
                },
                close: function(){
                    $("button[title='hideProgressBar']").show();
                }
            });
        });
    </script>
<?php
echo $OUTPUT->footer();
