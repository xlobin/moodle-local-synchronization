<?php

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('localsynchronization');

$fileStorage = get_file_storage();
var_dump($fileStorage);