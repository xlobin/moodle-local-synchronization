<?php

/*
 * Database MySQLDump Class File
 * Copyright (c) 2015 by Muhammad Bahjah modification https://code.google.com/p/db-mysqldump/
 * Muhammad Bahjah
 * GNU General Public License v3 http://www.gnu.org/licenses/gpl.html
 */

class MySQLDump {

    var $tables = array();
    var $connected = false;
    var $version = '0.1';
    var $output;
    var $droptableifexists = false;
    var $mysql_error;
    var $host, $user, $pass, $db;

    function __construct($config) {
        foreach ($config as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    function connect() {
        $return = true;
        $conn = @mysql_connect($this->host, $this->user, $this->pass);
        if (!$conn) {
            $this->mysql_error = mysql_error();
            $return = false;
        }
        $seldb = @mysql_select_db($this->db);
        if (!$conn) {
            $this->mysql_error = mysql_error();
            $return = false;
        }
        $this->connected = $return;
        return $return;
    }

    function list_tables() {
        $return = true;
        if (!$this->connected) {
            $return = false;
        }
        $this->tables = array();
        $sql = mysql_query("SHOW TABLES");
        while ($row = mysql_fetch_array($sql)) {
            array_push($this->tables, $row[0]);
        }
        return $return;
    }

    function list_values($tablename) {
        $sql = mysql_query("SELECT * FROM $tablename");
        $first = true;

        while ($row = mysql_fetch_array($sql)) {
            if ($first) {
                $this->output .= "\n\n-- Dumping data for table: $tablename\n\n";
                $first = false;
            }
            $broj_polja = count($row) / 2;
            $this->output .= "INSERT INTO `$tablename` VALUES(";
            $buffer = '';
            for ($i = 0; $i < $broj_polja; $i++) {
                $vrednost = $row[$i];
                if (!is_integer($vrednost)) {
                    $vrednost = "'" . addslashes($vrednost) . "'";
                }
                $buffer .= $vrednost . ', ';
            }
            $buffer = substr($buffer, 0, count($buffer) - 3);
            $this->output .= $buffer . ");\n";
        }
    }

    function dump_table($tablename) {
        $this->output = "";
        $this->get_table_structure($tablename);
        $this->list_values($tablename);
    }

    function get_table_structure($tablename) {
        $this->output .= "\n\n-- Dumping structure for table: $tablename\n\n";
        if ($this->droptableifexists) {
            $this->output .= "DROP TABLE IF EXISTS `$tablename`;\nCREATE TABLE `$tablename` (\n";
        } else {
            $this->output .= "CREATE TABLE `$tablename` (\n";
        }
        $sql = mysql_query("DESCRIBE $tablename");
        $this->fields = array();
        while ($row = mysql_fetch_array($sql)) {
            $name = $row[0];
            $type = $row[1];
            $null = $row[2];
            if (empty($null) || $null=='NO') {
                $null = "NOT NULL";
            }
            $key = $row[3];
            if ($key == "PRI") {
                $primary = $name;
            }
            $default = $row[4];
            $extra = $row[5];
            if ($extra !== "") {
                $extra .= ' ';
            }
            $this->output .= "  `$name` $type $null $extra,\n";
        }
        $this->output .= "  PRIMARY KEY  (`$primary`)\n);\n";
    }

    /**
     * 
     * @param boolean $asFile
     * @param string $fileName
     * @param string $path
     */
    function generate_dump($asFile = false, $fileName = '', $path = '') {
        global $CFG;
        
        ob_start();        

        //END xHTML 1.0 Strict Header Output. ------------------------------------------------

        $this->connect(); //Connect To Database
        if (!$this->connected) {
            die('Error: ' . $this->mysql_error);
        } //On Failed Connection, Show Error.
        $this->list_tables(); //List Database Tables.

        $broj = count($this->tables); //Count Database Tables.
        //START Page Header Output. ----------------------------------------------------------
       
        echo "-------------------------------------------------".PHP_EOL;
        echo "-- Database MySQLDump Class Script " . $this->version . " ---".PHP_EOL;
        echo "-------------------------------------------------".PHP_EOL;
        //END Page Header Output. ------------------------------------------------------------

        echo "\n-- Database Selected: $this->db on $this->host ($this->user:$this->pass)"; //Show Database, Server, User Name and Password.
        //START Database MySQL Dump. ---------------------------------------------------------
        echo "\n-- STARTING MYSQL DATABASE DUMP --";
        for ($i = 0; $i < $broj; $i++) {
            $table_name = $this->tables[$i]; //Get Table Names.
            $this->dump_table($table_name); //Dump Data to the Output Buffer.
            echo htmlspecialchars($this->output); //Display Output.
        }
        echo "\n-- END OF MYSQL DATABSE DUMP --\n";
        //END Database MySQL Dump. -----------------------------------------------------------

        $contents = ob_get_contents();
        ob_end_clean();
        
        if ($asFile){
            $fileName = (empty($fileName)) ? 'database_backup_'.date('ymdhis').'.sql' : $fileName ;
            $pathFolder = $CFG->dirroot.'/db_backup';
            $pathFile = $pathFolder.'/'.$fileName;
            if (!file_exists($pathFolder)){
                mkdir($pathFolder, 0700);
            }
            
            $fileSize = file_put_contents($pathFile, $contents);
            return (($fileSize) ? $pathFile : "");
        }else{
            echo $contents;
        }
    }

}

?>