<?php
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once(__DIR__ . '/lib/MyClient.php');

admin_externalpage_setup('localsynchronizationupgrade');

$status = optional_param('status', 0, PARAM_ALPHANUM);
$url = optional_param('url', 0, PARAM_URL);
$baseUrl = '/local/synchronization/newupgrade.php';
if (!empty($status) && !empty($url)) {
    $path = __DIR__;
    $pathFile = fopen($url, 'r');

    if ($pathFile) {
        $tempFile = $path . "/../Tmpfile.zip";
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        $putsFile = file_put_contents($tempFile, $pathFile);
        $success = false;
        if ($putsFile) {
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
                        return unlink($path);
                    }
                    return false;
                }

                Delete($path, false);
                $zip->extractTo($path . DIRECTORY_SEPARATOR . '..');
                $zip->close();

                redirect($CFG->wwwroot, 'Successfully upgrade modules.', 2);
            }

            redirect(new moodle_url($baseUrl), 'Failed upgrade modules.', 2);
        }
    } else {
        redirect(new moodle_url($baseUrl), 'Failed upgrade modules.', 2);
    }
}
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('upgrade_module', 'local_synchronization'));
if (!$CFG->enablewebservices) {
    echo $OUTPUT->notification(get_string('turnonwebservices', 'local_synchronization'), 'notifyproblem');
}

$table = new flexible_table('tbl_synchronize_from_server');

$table->define_columns(array('version', 'date', 'description', 'action'));
$table->define_headers(array(get_string('version', 'local_synchronization'), get_string('date', 'local_synchronization'),
    get_string('description', 'local_synchronization'),
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

$version = get_config('local_synchronization')->version;

$server_ip = get_config('local_synchronization', 'serverip');
$schoolid = get_config('local_synchronization', 'schoolid');
$token = get_config('local_synchronization', 'token');

$clientUpload = new MyClient($server_ip, $schoolid, $token);
$clientUpload->requestUpgradeVersion();
$responses = $clientUpload->getResponse(false);
$responses = json_decode($responses);
$urlDownload = new moodle_url($baseUrl);
foreach ($responses as $key => $value) {
    $value = (array)$value;
    if ($version < $value['version']) {
        $action = html_writer::link($urlDownload . '?url=' . $value['url'] . '&status=1', get_string('upgrade', 'local_synchronization'), array(
                    'class' => 'btn upload_btn',
        ));
        $value['date'] = (isset($value['date'])) ? $value['date'] : date('Y-m-d');
        $table->add_data(array(
            $value['version'],
            $value['date'],
            $value['description'],
            $action,
        ));
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
