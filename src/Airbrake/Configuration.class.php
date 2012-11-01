<?php
namespace Airbrake;

require_once 'Record.class.php';

use Airbrake\AirbrakeException as AirbrakeException;

/**
 * Airbrake configuration class.
 *
 * Loads via the inherited Record class methods.
 *
 * @package        Airbrake
 * @author         Drew Butler <drew@abstracting.me>
 * @copyright      (c) 2011 Drew Butler
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class Configuration extends Record
{
    protected $_apiKey;
    protected $_timeout = 2;
    protected $_delayedTimeout = 30;
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
    protected $_apiEndPoint                    = 'http://api.airbrake.io/notifier_api/v2/notices';
    protected $_validateXML                    = false;   // set to true to validate the generated XML against a XSD file (see the XML validation class)
    protected $_errorPrefix                    = null;    // appended to all reports' titles
    protected $_handleSeamlessly               = false;   // if true, it handles events seamlessly (ie they get logged in Airbrake but are still left uncaught to be logged further down - e.g. in the web server's logs)
    protected $_errorReporting                 = E_ALL;   // report only E_WARNING, E_PARSE and E_ERROR (cf http://php.net/manual/en/errorfunc.constants.php)
    protected $_silentExceptionClasses         = array(); // exception classes that won't be logged (nor re-thrown if the seamless mode is on)
    protected $_additionalParams               = array(); // any additional params to pass to Airbrake
    protected $_additionalParamsCallback       = array(); // callbacks to be called when constructing the notice
                                                          // each entry must be an array with 2 keys : 'callback' defining a callback function,
                                                          // and 'arguments' defining an array of arguments to be passed to the callback
                                                          // and finally, each callback must return an array (of params to be included in the notice)
    protected $_errorNotificationCallback      = null;    // a callback that takes an AirbrakeException as a argument
                                                          // used to notify the upper layer
    protected $_delayedNotificationClass       = null;    // a class to create delayed notification; this class *must* implement IDelayedNotification
    protected $_secondaryNotificationCallback  = null;    // a callback that takes an AirbrakeException as a argument
                                                          // used to notify the upper layer of secondary errors (like "over the limit" errors when notofying to Airbrake)

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
        if (($delayedNotifClass = $this->get('delayedNotificationClass'))
            && !array_key_exists('Airbrake\IDelayedNotification', class_implements($delayedNotifClass)))
        {
            throw new AirbrakeException("delayedNotificationClass $delayedNotifClass does not implement IDelayedNotification");
        }
    }

    public function getAdditionalParams()
    {
        $result = $this->get('additionalParams');
        foreach ($this->get('additionalParamsCallback') as $params) {
            try {
                // check the syntax is correct
                if (! (is_array($params)
                    && array_key_exists('callback', $params)
                    && is_callable($params['callback'], false, $callableName)
                    && array_key_exists('arguments', $params)
                    && is_array($params['arguments'])) )
                {
                    throw new Exception('Incorrect additional callback, check syntax:\n'.var_export($params, true));
                }
                // call it, and if the result is an array, keep it!
                $callbackResult = call_user_func_array($params['callback'], $params['arguments']);
                if (!is_array($callbackResult)) {
                    throw new Exception('Callback must return an array! '.$callableName.' returned a type '.gettype($callbackResult).' :\n'.var_export($callbackResult, true));
                }
                $result = array_merge($result, $callbackResult);
            } catch (Exception $e) {
                // notify the upper layer, but keep reporting the current error anyway
                $this->notifyUpperLayer($e);
            }
        }
        return $result;
    }

    public function addAdditionalParam($key, $value)
    {
        $params = $this->getAdditionalParams();
        $params[$key] = $value;
    }

    public function notifyUpperLayer(\Exception $e, $rethrowIfNoCallback = false, $secondaryNotification = false)
    {
        if ($e instanceof AirbrakeException) {
            $airbrakeException = $e;
        } else {
            $airbrakeException = new AirbrakeException($e->getMessage());
        }
        $callback = null;
        if ($secondaryNotification) {
            $callback = $this->get('secondaryNotificationCallback');
        }
        if (!$callback) {
            $callback = $this->get('errorNotificationCallback');
        }
        if ($callback && !is_callable($callback)) {
            $callback = null;
        }
        if ($callback) {
            try {
                call_user_func_array($callback, array($airbrakeException));
            } catch (Exception $ignored) { }
        } elseif($rethrowIfNoCallback) {
            throw $airbrakeException;
        }
    }

}
