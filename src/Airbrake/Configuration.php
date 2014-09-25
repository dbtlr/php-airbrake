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
     * @param array|\stdClass $data
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
        if ($this->get('serverData') === null) {
            $this->set('serverData', (array) $_SERVER);
        }

        if ($this->get('getData') === null) {
            $this->set('getData', (array) $_GET);
        }

        if ($this->get('postData') === null) {
            $this->set('postData', (array) $_POST);
        }

        if ($this->get('sessionData') === null && isset($_SESSION)) {
            $this->set('sessionData', (array) $_GET);
        }

        $serverData = $this->get('serverData');

        if (!$this->get('projectRoot')) {

            $projectRoot = isset($serverData['_']) ? $serverData['_'] : $serverData['DOCUMENT_ROOT'];
            $this->set('projectRoot', $projectRoot);
        }

        if (!$this->get('url')) {
            if (isset($serverData['REDIRECT_URL'])) {
                $this->set('url', $serverData['REDIRECT_URL']);
            } elseif (isset($serverData['SCRIPT_NAME'])) {
                $this->set('url', $serverData['SCRIPT_NAME']);
            }
        }

        if (!$this->get('hostname')) {
            $this->set('hostname', isset($serverData['HTTP_HOST']) ? $serverData['HTTP_HOST'] : 'No Host');
        }

        $protocol = $this->get('secure') ? 'https' : 'http';
        $endPoint = $this->get('apiEndPoint') ?: $protocol . '://' . $this->get('host') . $this->get('resource');
        $this->set('apiEndPoint', $endPoint);
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
        foreach ($this->get('parameterFilters') as $filter) {
            /** @var \Airbrake\Filter\FilterInterface $filter */
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
     * @param string|Filter\FilterInterface $keyName
     * @return self
     */
    public function addFilter($keyName)
    {
        if (!$keyName instanceof Filter\FilterInterface) {
            $keyName = new Filter($keyName);
        }

        $this->_parameterFilters[] = $keyName;
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
     * @param array $keyNames
     * @return Configuration
     */
    public function addFilters($keyNames)
    {
        array_map(array($this, 'addFilter'), $keyNames);
        return $this;
    }

    /**
     * Clears the GET/POST request key name black list.
     *
     * @return Configuration
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
        if (!$this->get('apiKey')) {
            throw new AirbrakeException(
                'Cannot initialize the Airbrake client without an ApiKey being set to the configuration.'
            );
        }
    }
}
