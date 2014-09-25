<?php
namespace Airbrake;

use Airbrake\Exception as AirbrakeException;

/**
 * Airbrake configuration class.
 *
 * Loads via the inherited Record class methods.
 *
 * @package    Airbrake
 * @author     Drew Butler <drew@dbtlr.com>
 * @copyright  (c) 2011-2013 Drew Butler
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
class Configuration extends Record
{
    protected $_apiKey;
    protected $_timeout = 2;
    protected $_environmentName = 'production';
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
    protected $_secure = false;
    protected $_host = 'api.airbrake.io';
    protected $_resource = '/notifier_api/v2/notices';
    protected $_apiEndPoint;
    protected $_errorReportingLevel;

    protected $_parameterFilters = array();

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
     */
    protected function initialize()
    {
        if ($this->serverData === null) {
            $this->serverData = (array) $_SERVER;
        }

        if ($this->getData === null) {
            $this->getData = (array) $_GET;
        }

        if ($this->postData === null) {
            $this->postData = (array) $_POST;
        }

        if ($this->sessionData === null && isset($_SESSION)) {
            $this->sessionData = (array) $_SESSION;
        }

        if (!$this->projectRoot) {
            $this->projectRoot = isset($this->serverData['_']) ? $this->serverData['_'] : $this->serverData['DOCUMENT_ROOT'];
        }

        if (!$this->url) {
            if (isset($this->serverData['REDIRECT_URL'])) {
                $this->url = $this->serverData['REDIRECT_URL'];
            } elseif (isset($this->serverData['SCRIPT_NAME'])) {
                $this->url = $this->serverData['SCRIPT_NAME'];
            }
        }

        if (!$this->hostname) {
            $this->hostname = isset($this->serverData['HTTP_HOST']) ? $this->serverData['HTTP_HOST'] : 'No Host';
        }

        $protocol = $this->secure ? 'https' : 'http';
        $this->apiEndPoint = $this->apiEndPoint ?: $protocol.'://'.$this->host.$this->resource;
    }

    /**
     * Get the combined server parameters. Note that these parameters will be 
     * filtered according to a black list of key names to ignore. If you wish to 
     * get the unfiltered results you should use the getUnfilteredParameters
     * method instead.
     *
     * @return array
     */
    public function getParameters()
    {
        $parameters = $this->getUnfilteredParameters();
        foreach($this->_parameterFilters as $filter) {
            $filter->filter($parameters);
        }
        return $parameters;
    }

    /**
     * Get the combined server parameters without applying the registered 
     * filters
     *
     * @return array
     */
    public function getUnfilteredParameters()
    {
        return array_merge($this->get('postData'), $this->get('getData'));
    }

    /**
     * Adds an entry to a black list of GET/POST parameter key names which 
     * should not be sent to the Airbrake server. This should be used to prevent
     * sensitive information, such as passwords or credit card details from
     * leaving your application server via error logging.
     *
     * Nested keys are treated like html form names - e.g. the key name 
     * my_form[id] would stop the value inside $_POST['my_form']['id'] 
     * from being sent.
     * 
     * @param string|Airbrake\Filter\FilterInterface $key_name
     * @return Airbrake\Configuration
     */
    public function addFilter($key_name)
    {
        if (!($key_name instanceof Filter\FilterInterface)){
            $key_name = new Filter($key_name);
        }
        $this->_parameterFilters[] = $key_name;
        return $this;
    }

    /**
     * Adds an array of entries to a black list of GET/POST parameter key names
     * which should not be sent to the Airbrake server. This should be used to
     * prevent sensitive information, such as passwords or credit card details 
     * from leaving your application server via error logging.
     *
     * Nested keys are treated like html form names - e.g. the key name 
     * my_form[id] would stop the value inside $_POST['my_form']['id'] 
     * from being sent.
     *
     * @param array $key_names
     * @return Airbrake\Configuration
     */
    public function addFilters($key_names)
    {
        array_map(array($this, 'addFilter'), $key_names);
        return $this;
    }

    /**
     * Clears the GET/POST request key name black list.
     *
     * @return Airbrake\Configuration
     */
    public function clearFilters()
    {
        $this->_parameterFilters = array();
        return $this;
    }

    /**
     * Verify that the configuration is complete.
     */
    public function verify()
    {
        if (!$this->apiKey) {
            throw new AirbrakeException('Cannot initialize the Airbrake client without an ApiKey being set to the configuration.');
        }
    }
}
