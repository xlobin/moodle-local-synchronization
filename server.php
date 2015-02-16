<?php
/**
 * NO_DEBUG_DISPLAY - disable moodle specific debug messages and any errors in output
 */
define('NO_DEBUG_DISPLAY', true);
define('WS_SERVER', true);
define('NO_MOODLE_COOKIES', true);

require('../../config.php');
require_once("$CFG->dirroot/webservice/rest/locallib.php");

if (!webservice_protocol_is_enabled('rest')) {
    header("HTTP/1.0 403 Forbidden");
    debugging('The server died because the web services or the REST protocol are not enable',
        DEBUG_DEVELOPER);
    die;
}


/**
 * CORS REST server. Authentication done using custom user and tokens.
 *
 * @package    local_synchronization
 * @copyright  2015 Muhammad Bahjah <lobin.hoop@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webservice_corsrest_server extends webservice_rest_server {

    /**
     * Internal implementation - sending of page headers.
     */
    protected function send_headers() {
        if ($this->restformat == 'json') {
            header('Content-type: application/json');
        } else {
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: inline; filename="response.xml"');
        }
        header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
        header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
        header('Pragma: no-cache');
        header('Accept-Ranges: none');
        // Enable cross domain requests.
        header('Access-Control-Allow-Origin: *');
    }
    
    /**
     * Override parent authentication using custom user and token
     * @global type $DB
     * @param string $tokentype
     * @return object
     * @throws moodle_exception
     */
    protected function authenticate_by_token($tokentype){
        global $DB;

        $loginfaileddefaultparams = array(
            'context' => context_system::instance(),
            'other' => array(
                'method' => $this->authmethod,
                'reason' => null
            )
        );
        $token = explode(',', $this->token);
        $schoolToken = $token[0];
        $schoolID = $token[1];

        if (!$token = $DB->get_record('local_school', array('verified' => true, 'school_id' => $schoolID, 'school_key' => $schoolToken))) {
            // Log failed login attempts.
            $params = $loginfaileddefaultparams;
            $params['other']['reason'] = 'invalid_token';
            $event = \core\event\webservice_login_failed::create($params);
            $event->set_legacy_logdata(array(SITEID, 'webservice', get_string('tokenauthlog', 'webservice'), '' ,
                get_string('failedtolog', 'webservice').": ".$this->token. " - ".getremoteaddr() , 0));
            $event->trigger();
            throw new moodle_exception('invalidtoken', 'webservice');
        }

        $user = $DB->get_record('user', array('id'=> 2), '*', MUST_EXIST);
        $this->restricted_context = context::instance_by_id(1);
        $this->restricted_serviceid = $token->externalserviceid;

        return $user;

    }
}
$server = new webservice_corsrest_server(WEBSERVICE_AUTHMETHOD_PERMANENT_TOKEN);
$server->run();
die;