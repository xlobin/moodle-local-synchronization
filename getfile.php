<?php

require_once('../../config.php');

$id = optional_param('id', 0, PARAM_INT);
if (!empty($id)) {
    $context = context_course::instance($id);

    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'backup', 'course', false, 'timecreated');

    $myfiles = array_reverse($files);
    $backup_file = '';
    foreach ($myfiles as $file) {
        if ($file->get_filename() === 'backup.mbz') {
            $backup_file = $file;
            break;
        }
    };
    send_file($backup_file, $backup_file->get_filename());
}