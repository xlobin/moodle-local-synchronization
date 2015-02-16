<?php

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('localdatabasebackup');

$newBackup = optional_param('newbackup', 0, PARAM_INT);
$uploadBackup = optional_param('upload', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);
$spage = optional_param('spage', 0, PARAM_INT);
$ssort = optional_param('ssort', 'time', PARAM_ALPHANUMEXT);
$perpage = 20;
$baseUrl = new moodle_url('/local/synchronization/database_backup.php');

if ($newBackup) {
    require_once(__DIR__ . '/lib/mysqldump.php');
    $backup = new MySQLDump(array(
        'droptableifexists' => false,
        'host' => $CFG->dbhost,
        'user' => $CFG->dbuser,
        'pass' => $CFG->dbpass,
        'db' => $CFG->dbname,
    ));

    if ($path = $backup->generate_dump(true)) {
        $record = new stdClass();
        $record->time = date('Y-m-d H:i:s');
        $record->executor = $USER->username;
        $record->file_location = $path;
        $record->status = 0;
        $lastinsertid = $DB->insert_record('ls_backupdatabaselog', $record, false);
        redirect($baseUrl, 'Successfully Created new backup.', 2);
    }
    core_plugin_manager::reset_caches();
    admin_get_root(true, false);  // settings not required - only pages
    redirect($baseUrl, 'Failed Created new backup.', 2);
} else if (!empty($uploadBackup) && !empty($id)) {
    require_once(__DIR__ . '/lib/MyClient.php');
    $backupRecord = $DB->get_record('ls_backupdatabaselog', array('id' => $id));
    $record = new stdClass();
    $record->id = $id;
    $record->status = 1;

    $server_ip = get_config('local_synchronization', 'serverip');
    $schoolid = get_config('local_synchronization', 'schoolid');
    $token = get_config('local_synchronization', 'token');

    $clientUpload = new MyClient($server_ip, $schoolid, $token);
    $clientUpload->requestUpload(array(
        'file' => $backupRecord->file_location
    ));
    $responses = $clientUpload->getResponse(false);
    $responses = json_decode($responses);
    if ($responses->success && $DB->update_record('ls_backupdatabaselog', $record, false)) {
        redirect($baseUrl, 'Successfully Upload new backup.', 2);
    }
    redirect($baseUrl, 'Failed Upload new backup.', 2);
}

$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');

echo $OUTPUT->header();
$urlNewBackup = new moodle_url('/local/synchronization/database_backup.php', array(
    'newbackup' => 1,
        ));
$newBackup = html_writer::link($urlNewBackup, get_string('newbackup', 'local_synchronization'), array(
            'class' => 'btn pull-right upload_btn'
        ));
echo $OUTPUT->heading(get_string('database_backup', 'local_synchronization') . $newBackup);

if (!$CFG->enablewebservices) {
    echo $OUTPUT->notification(get_string('turnonwebservices', 'local_synchronization'), 'notifyproblem');
}

$table = new flexible_table('tbl_backupdatabaselog');

$table->define_columns(array('time', 'executor', 'file_location', 'action'));
$table->define_headers(array(get_string('time', 'local_synchronization'), get_string('executor', 'local_synchronization'), get_string('file_location', 'local_synchronization'), get_string('action', 'local_synchronization')));
$table->set_control_variables(array(
    TABLE_VAR_SORT => 'ssort',
    TABLE_VAR_IFIRST => 'sifirst',
    TABLE_VAR_ILAST => 'silast',
    TABLE_VAR_PAGE => 'spage'
));
$table->define_baseurl($baseUrl);
$table->set_attribute('class', 'admintable blockstable generaltable');
$table->set_attribute('id', 'ls_backupdatabase_table');

$jumlahBackupLog = $DB->count_records('ls_backupdatabaselog');
$table->pagesize($perpage, $jumlahBackupLog);
$table->sortable(true, 'time', SORT_DESC);
$table->no_sorting('action');
$table->set_attribute('cellspacing', '0');
$table->setup();
$sort = $table->get_sql_sort();
$backupLog = $DB->get_records('ls_backupdatabaselog', array(), $sort, '*', ($spage * $perpage), $perpage);
$urlUploadBackup = new moodle_url('/local/synchronization/database_backup.php', array(
    'upload' => 1,
        ));
foreach ($backupLog as $key => $value) {
    $action = ($value->status == 0) ? html_writer::link($urlUploadBackup . '&id=' . $value->id, get_string('uploadbackup', 'local_synchronization'), array(
                'class' => 'btn upload_btn',
                'id' => 'upload_btn_'.$value->id
            )) : '';
    $table->add_data(array(
        $value->time,
        $value->executor,
        $value->file_location,
        $action,
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
            width: 250,
            height: 80,
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
