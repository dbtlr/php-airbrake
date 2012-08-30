<?php
namespace Airbrake;

require_once 'Record.class.php';

use Airbrake\AirbrakeException as AirbrakeException;

/**
 * Airbrake configuration class.
 *
 * Loads via the inherited Record class methods.
 *
 * @package		Airbrake
 * @author		Drew Butler <drew@abstracting.me>
 * @copyright	(c) 2011 Drew Butler
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Configuration extends Record
{
    protected $_apiKey;
    protected $_timeout = 2;
    protected $_environmentName = 'prod';
    protected $_serverData;
    protected $_getData;
    protected $_postData;
    protected $_sessionData;
    protected $_component;
    protected $_action;
    protected $_projectRoot;
    protected $_url;
    protected $_hostname;
    protected $_queue;
    protected $_apiEndPoint  = 'http://api.airbrake.io/notifier_api/v2/notices';
    protected $_additionalParams = array(); // any additional params to pass to Airbrake
    protected $_validateXML = false; // set to true to validate the generated XML against a XSD file (see the XML validation class)
    protected $_errorPrefix = null; // appended to all reports' titles
    protected $_handleSeamlessly = false; // if true, it handles events seamlessly (ie they get logged in Airbrake but are still left uncaught to be logged further down - e.g. in the web server's logs)
    protected $_errorReporting = E_ALL; // report only E_WARNING, E_PARSE and E_ERROR (cf http://php.net/manual/en/errorfunc.constants.php)

    /**
     * Load the given data array to the record.
     *
     * @param string $apiKey
     * @param array|stdClass $data
     */
    public function __construct($apiKey, $data = array())
    {
        $data['apiKey'] = $apiKey;
        parent::__construct($data);
    }

    /**
     * Initialize the data source.
     * put any additional params you'd like to be included in the Airbrake record in the 'additionalParams' key
     */
    protected function initialize()
    {
        if (!$this->serverData) {
            $this->serverData = (array) $_SERVER;
        }
        if (!$this->getData) {
            $this->getData = (array) $_GET;
        }
        if (!$this->postData) {
            $this->postData = (array) $_POST;
        }

        if (!$this->sessionData && isset($_SESSION)) {
            $this->sessionData = (array) $_SESSION;
        }

        if (!$this->projectRoot) {
            $this->projectRoot = isset($this->serverData['_']) ? $this->serverData['_'] : $this->serverData['DOCUMENT_ROOT'];
        }

        if (!$this->url) {
            $this->url = isset($this->serverData['REDIRECT_URL']) ? $this->serverData['REDIRECT_URL'] : $this->serverData['SCRIPT_NAME'];
        }

        if (!$this->hostname) {
            $this->hostname = isset($this->serverData['HTTP_HOST']) ? $this->serverData['HTTP_HOST'] : 'No Host';
        }
    }

    /**
     * Get the combined server parameters.
     *
     * @return array
     */
    public function getParameters()
    {
        return array_merge($this->get('postData'), $this->get('getData'));
    }

    /**
     * Verify that the configuration is complete.
     */
    public function verify()
    {
        if (!$this->apiKey) {
            throw new AirbrakeException(
                'Cannot initialize the Airbrake client without an ApiKey being set in the configuration.');
        }
    }

    public function getAdditionalParams()
    {
        return $this->get('additionalParams');
    }

    public function addAdditionalParam($key, $value)
    {
        $params = $this->getAdditionalParams();
        $params[$key] = $value;
    }

}
