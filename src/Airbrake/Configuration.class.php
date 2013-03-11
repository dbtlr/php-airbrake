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
    # old endpoint: http://api.airbrake.io/notifier_api/v2/notices
    # see: http://help.airbrake.io/kb/accounts-2/introducing-airbrake-v2
    protected $_apiEndPoint                    = 'http://collect.airbrake.io/notifier_api/v2/notices';
    protected $_validateXML                    = false;   // set to true to validate the generated XML against a XSD file (see the XML validation class)
    protected $_errorPrefix                    = null;    // appended to all reports' titles
    protected $_handleSeamlessly               = false;   // if true, it handles events seamlessly (ie they get logged in Airbrake but are still left uncaught to be logged further down - e.g. in the web server's logs)
    protected $_errorReporting                 = E_ALL;   // report only E_WARNING, E_PARSE and E_ERROR (cf http://php.net/manual/en/errorfunc.constants.php)
    protected $_silentExceptionClasses         = array(); // exception classes that won't be logged (nor re-thrown if the seamless mode is on)
    protected $_additionalCgiParams            = array(); // any additional CGI params to pass to Airbrake
    protected $_additionalCgiParamsCallbacks   = array(); // callbacks to be called when constructing the notice
                                                          // each entry must be an array with 2 keys : 'callback' defining a callback function,
                                                          // and 'arguments' defining an array of arguments to be passed to the callback
                                                          // and finally, each callback must return an array (of params to be included in the notice)
    protected $_additionalReqParamsCallbacks   = array(); // same as 'additionalCgiParamsCallbacks', but for request params instead
    protected $_errorNotificationCallback      = null;    // a callback that takes an AirbrakeException as a argument
                                                          // used to notify the upper layer
    protected $_delayedNotificationClass       = null;    // a class to create delayed notification; this class *must* implement IDelayedNotification
    protected $_secondaryNotificationCallback  = null;    // a callback that takes an AirbrakeException as a argument
                                                          // used to notify the upper layer of secondary errors (like "over the limit" errors when notofying to Airbrake)
    protected $_arrayReportDatabaseClass       = null;    // a class to log Airbrake reports in a local DB; this class *must* implement IArrayReportDatabaseObject
    protected $_sendArgumentsToAirbrake        = true;    // if turned off, we won't send function arguments to Airbrake (you might want to use that to avoid including
                                                          // sensitive data in your Airbrake reports)
    protected $_blacklistedScalarArgsCallback  = null;    // a callback that returns an array of scalars that won't ever be reported in the backtraces' arguments
    protected $_blacklistedRegexArgsCallback   = null;    // a callback that returns an array of regular expressions that will prevent any scalar matching them from being
                                                          // included into the backtraces' arguments


    /* Interval vars, not to be set by the user */
    protected $__blacklistedScalarArgsCache    = null;
    protected $__blacklistedRegexArgsCache     = null;
    protected $__blacklistCacheComputed        = false;


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
     * put any additional CGI params you'd like to be included in the Airbrake record in the 'additionalCgiParams' key
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
        $this->checkOptionClassImplements('delayedNotificationClass', 'IDelayedNotification');
        $this->checkOptionClassImplements('arrayReportDatabaseClass', 'IArrayReportDatabaseObject');
    }

    // throws an exception if the given key is set to a class that doesn't implement the given interface
    private function checkOptionClassImplements($key, $interface)
    {
        if (($class = $this->get($key))
            && !array_key_exists('Airbrake\\'.$interface, class_implements($class)))
        {
            throw new AirbrakeException("$key $class does not implement $interface");
        }
    }

    public function addAdditionalCgiParam($key, $value)
    {
        $params = $this->getAdditionalCgiParams();
        $params[$key] = $value;
    }

    public function getAdditionalCgiParams()
    {
        $result = $this->get('additionalCgiParams');
        $result = array_merge($result, $this->aggregateCallbackResults($this->get('additionalCgiParamsCallbacks')));
        return $result;
    }

    public function getAdditionalReqParams()
    {
        return $this->aggregateCallbackResults($this->get('additionalReqParamsCallbacks'));
    }

    private function aggregateCallbackResults(array $callbacks)
    {
        $result = array();
        foreach ($callbacks as $params) {
            $callbackResult = $this->callAdditionalCallback($params);
            $result = array_merge($result, $callbackResult);
        }
        return $result;
    }

    private function callAdditionalCallback(array $callbackParams)
    {
        // check that the syntax is correct
        if (!is_array($callbackParams)
            || !array_key_exists('callback', $callbackParams)
            || !array_key_exists('arguments', $callbackParams)
            || !is_array($callbackParams['arguments'])
            )
        {
            $this->notifyUpperLayer(new \Exception('Incorrect callback, check syntax:\n'.var_export($callbackParams, true)));
        }
        return $this->executeCallbackReturningArray($callbackParams['callback'], $callbackParams['arguments']);
    }

    // executes a callback that's expected to return an arrray
    private function executeCallbackReturningArray($callback, array $arguments = array())
    {
        try {
            if (!is_callable($callback, false, $callableName)) {
                throw new \Exception(var_export($callback, true).' is not a valid callback!');
            }
            // call it, and keep the result if it's an array
            $callbackResult = call_user_func_array($callback, $arguments);
            if (!is_array($callbackResult)) {
                throw new \Exception('Callback must return an array! '.$callableName.' returned a type '.gettype($callbackResult).' :\n'.var_export($callbackResult, true));
            }
            return $callbackResult;
        } catch (\Exception $e) {
            // notify the upper layer, but keep reporting the current error anyway
            $this->notifyUpperLayer($e);
            return array();
        }
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
            } catch (\Exception $ignored) { }
        } elseif($rethrowIfNoCallback) {
            throw $airbrakeException;
        }
    }

    private function buildBlacklistCache()
    {
        if ($this->get('_blacklistCacheComputed')) {
            return;
        }
        $this->set('_blacklistCacheComputed', true);
        // scalars
        if ($scalarCallback = $this->get('blacklistedScalarArgsCallback')) {
            $scalarCache = $this->executeCallbackReturningArray($scalarCallback);
        } else {
            $scalarCache = array();
        }
        $this->set('_blacklistedScalarArgsCache', $scalarCache);
        // and regexes
        if ($regexCallback = $this->get('blacklistedRegexArgsCallback')) {
            $regexCache = $this->executeCallbackReturningArray($regexCallback);
        } else {
            $regexCache = array();
        }
        $this->set('_blacklistedRegexArgsCache', $regexCache);
    }

    public function isScalarBlackListed($arg)
    {
        $arg = (string) $arg;
        $this->buildBlacklistCache();
        if (in_array($arg, $this->get('_blacklistedScalarArgsCache'))) {
            return true;
        }
        foreach ($this->get('_blacklistedRegexArgsCache') as $regex) {
            if (preg_match($regex, $arg)) {
                return true;
            }
        }
        return false;
    }

}
