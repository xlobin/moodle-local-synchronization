<?php
/**
 * CORS REST client.
 *
 * @package    local_synchronization
 * @copyright  2015 Muhammad Bahjah <lobin.hoop@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MyClient {

    public $token = '';
    public $school_id = '';
    public $serverip = 'localhost';
    public $restformat = 'xml';
    private $_responses;

    /**
     * Constructor client REST
     * @param string $serverip
     * @param string $school_id
     * @param string $token
     */
    public function __construct($serverip, $school_id, $token) {
        if (!empty($serverip)) {
            if ($serverip[strlen($serverip) - 1] == '/') {
                $serverip = substr($serverip, 0, strlen($serverip) - 1);
            }
            $this->serverip = $serverip;
            if (strpos($serverip, 'http') === false) {
                $this->serverip = 'http://' .  $this->serverip;
            }
        }

        if (!empty($token)) {
            $this->token .= $token;
        }

        if (!empty($school_id)) {
            $this->token .= ',' . $school_id;
        }
    }

    /**
     * Request to server
     */
    public function request($params = array()) {
        //domain name
        $domainname = $this->serverip;
        //function name
        $functionname = 'local_schoolreg_getcontent';
        //server url
        $serverurl = $domainname . '/local/schoolreg/server.php?wstoken=' . $this->token . '&wsfunction=' . $functionname;
        $restformat = ($this->restformat == 'json') ? '&moodlewsrestformat=' . $this->restformat : '';
        $this->send($serverurl . $restformat, $params);
    }

    /**
     * send request
     * @param type $url
     * @param type $params
     */
    private function send($url, $params) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible;)");
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        $this->_responses = curl_exec($ch);
//        var_dump($this->_responses);
//        exit();
    }

    /**
     * Request to server with upload
     */
    public function requestUpload($params = array()) {
        //domain name
        $domainname = $this->serverip;
        //server url
        $serverurl = $domainname . '/local/schoolreg/upload.php';

        //Note: check "Maximum uploaded file size" in your Moodle "Site Policies".
        $filePath = $params['file']; //CHANGE THIS !
        $params = array('database_backup' => "@" . $filePath, 'token' => $this->token);

        $this->send($serverurl, $params);
    }

    /**
     * Request to server with upload
     */
    public function requestUpgradeVersion($params = array()) {
        //domain name
        $domainname = $this->serverip;
        //server url
        $serverurl = $domainname . '/local/schoolreg/upgrade_version.php';

        //Note: check "Maximum uploaded file size" in your Moodle "Site Policies".
        $params = array('token' => $this->token);

        $this->send($serverurl, $params);
    }

    public function requestUploadSynch($params = array()) {
        //domain name
        $domainname = $this->serverip;
        //server url
        $serverurl = $domainname . '/local/schoolreg/uploadSync.php';

        //Note: check "Maximum uploaded file size" in your Moodle "Site Policies".
        $filePath = $params['file']; //CHANGE THIS !
        $params = array('file_path' => "@" . $filePath, 'token' => $this->token, 'version' => $params['version']);

        $this->send($serverurl, $params);
    }

    /**
     * Response from REST Webservice
     * @param boolean $parseAsArray
     * @return mixed
     */
    public function getResponse($parseAsArray = true) {
        return ($parseAsArray) ? simplexml_load_string($this->_responses) : $this->_responses;
    }

}
