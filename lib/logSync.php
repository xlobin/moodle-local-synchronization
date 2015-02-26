<?php

/*
 * Database MySQLDump Class File
 * Copyright (c) 2015 by Muhammad Bahjah modification https://code.google.com/p/db-mysqldump/
 * Muhammad Bahjah
 * GNU General Public License v3 http://www.gnu.org/licenses/gpl.html
 */

class logSync {

    private $_pathFile = '';

    /**
     * 
     * @param boolean $asFile
     * @param string $fileName
     * @param string $path
     */
    function generate_dump($asFile = false, $fileName = '', $path = '') {
        global $CFG, $DB;

        $lists = $DB->get_records('synch_log_item', array('status' => '0'));

        if (count($lists) < 1) {
            return "";
        }
        $contents = json_encode($lists);

        if ($asFile) {
            $fileName = (empty($fileName)) ? 'sync_' . date('ymdhis') . '.json' : $fileName;
            $pathFolder = $CFG->dirroot . '/synch';
            $pathFile = $pathFolder . '/' . $fileName;
            if (!file_exists($pathFolder)) {
                mkdir($pathFolder, 0700);
            }

            $fileSize = file_put_contents($pathFile, $contents);
            if ($fileSize) {
                $sql = "UPDATE {synch_log_item} SET status = 1";
                $DB->execute($sql);
            }
            $this->_pathFile = $pathFile;
            return (($fileSize) ? $pathFile : "");
        } else {
            echo $contents;
        }
    }

    /**
     * drop file of sync.json
     * @param string $path
     */
    function dropDump($path = '') {
        $filePath = (!empty($path)) ? $path : $this->_pathFile;
        if (file_exists($filePath) && $filePath != '' && stripos($filePath, '.json')) {
            unlink($filePath);
        }
    }

}

?>