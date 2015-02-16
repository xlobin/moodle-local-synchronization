<?php

namespace local_synchronization\task;

class synchronize_update extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('newsynchronization', 'local_synchronization');
    }

    public function execute() {
        global $DB, $CFG;

        $enable = get_config('local_synchronization', 'enable_to_server');
        if ((boolean) $enable) {
            require_once($CFG->dirroot . '/local/synchronization/lib/logSync.php');
            require_once($CFG->dirroot . '/local/synchronization/lib/MyClient.php');

            $log = new \logSync();
            echo '-- Creating Package --';
            if ($path = $log->generate_dump(true)) {
                if (!empty($path)) {
                    $record = new \stdClass();
                    $record->time = date('Y-m-d H:i:s');
                    $record->file_location = $path;
                    $record->version = time();
                    $record->status = 0;
                    $id = $DB->insert_record('ls_synchronizelog', $record, false);

                    $record = $DB->get_record('ls_synchronizelog', array('id' => $id));
                    $record->status = 1;

                    $server_ip = get_config('local_synchronization', 'serverip');
                    $schoolid = get_config('local_synchronization', 'schoolid');
                    $token = get_config('local_synchronization', 'token');

                    $clientUpload = new \MyClient($server_ip, $schoolid, $token);

                    echo '-- Uploading Package --';
                    $clientUpload->requestUploadSynch(array(
                        'file' => $record->file_location,
                        'version' => $record->version
                    ));
                    $responses = $clientUpload->getResponse(false);
                    if ($responses) {
                        $responses = json_decode($responses);
                        if ($responses->success && $DB->update_record('ls_synchronizelog', $record, false)) {
                            echo '-- Successfully creating new synchronization --';
                        }
                    } else {
                        echo '-- Successfully creating new synchronization --';
                    }
                }
            }
        }
    }

}
