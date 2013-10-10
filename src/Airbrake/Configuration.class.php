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
    // meta-data to communicate with Sentry
    protected $_apiKey;
    protected $_apiEndPoint = 'https://app.getsentry.com/api/store/';
    protected $_platform    = 'php';

    // what to report, and what to add in the reports
    protected $_handleSeamlessly                = false;   // if true, it handles events seamlessly (ie they get logged in Airbrake but are still left uncaught to be logged further down - e.g. in the web server's logs)
    protected $_errorReporting                  = E_ALL;   // (cf http://php.net/manual/en/errorfunc.constants.php)
    protected $_silentExceptionClasses          = array(); // exception classes that won't be logged (nor re-thrown if the seamless mode is on)
    protected $_tagsCallback                    = null;    // an AirbrakeCallback to build the tags to include in every report - must return an array
    protected $_extraCallback                   = null;    // exact same as 'tagsCallback', except it's for extras
    protected $_interfacesCallback              = null;    // yet another AirbrakeCallback, for Sentry interfaces (see http://sentry.readthedocs.org/en/latest/developer/interfaces/index.html)
                                                           // must return an array mapping interfaces' names with their content
    protected $_sendArgumentsToAirbrake         = true;    // if turned off, we won't send function arguments to Airbrake (you might want to use that to avoid including
                                                           // sensitive data in your Airbrake reports)
    protected $_blacklistedScalarArgsCallback   = null;    // an AirbrakeCallback that returns an array of scalars that won't ever be reported in the backtraces' arguments
    protected $_blacklistedRegexArgsCallback    = null;    // an AirbrakeCallback that returns an array of regular expressions that will prevent any scalar matching them from being
                                                           // included into the backtraces' arguments
    protected $_blacklistedStringsInMsgCallback = null;    // an AirbrakeCallback that returns an array of strings that will be redacted if they appear in an error message

    // timeouts
    protected $_timeout        = 2;  // timeout when reporting in real time
    protected $_delayedTimeout = 30; // timeout when reporting offline

    // misc
    protected $_trustConfig               = false; // if set to true, the config won't be checked (makes things a little more efficient in prod environments)
    protected $_arrayReportDatabaseClass  = null;  // a class to log Airbrake reports in a local DB; this class *must* implement IArrayReportDatabaseObject
    protected $_delayedNotificationClass  = null;  // a class to create delayed notification; this class *must* implement IDelayedNotification
    protected $_objectStringifierCallback = null;  // an AirbrakeCallback that can generate custom string representations for objects
                                                   // to be included in the backtrace

    // notify the upper layer if something goes wrong while reporting an event
    protected $_errorNotificationCallback      = null;    // a simple callback that takes a single AirbrakeException as a argument
                                                          // used to notify the upper layer
    protected $_secondaryNotificationCallback  = null;    // exact same as 'errorNotificationCallback'
                                                          // used to notify the upper layer of secondary errors (like "over the limit" errors when notifying to Airbrake)


    private static $instance = null;

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
        self::$instance = $this;
    }

    /**
     * Verify that the configuration is complete.
     */
    public function verify()
    {
        if (!$this->trustConfig) {
            if (!$this->apiKey) {
                throw new AirbrakeException('Cannot initialize the Airbrake client without an ApiKey being set in the configuration.');
            }
            $this->checkOptionClassImplements('delayedNotificationClass', 'IDelayedNotification');
            $this->checkOptionClassImplements('arrayReportDatabaseClass', 'IArrayReportDatabaseObject');
            $this->checkAndSetAirbrakeCallback('tagsCallback', array(), 'array');
            $this->checkAndSetAirbrakeCallback('extraCallback', array(), 'array');
            $this->checkAndSetAirbrakeCallback('interfacesCallback', array(), 'array');
            $this->checkAndSetAirbrakeCallback('blacklistedScalarArgsCallback', array(), 'array');
            $this->checkAndSetAirbrakeCallback('blacklistedRegexArgsCallback', array(), 'array');
            $this->checkAndSetAirbrakeCallback('objectStringifierCallback', '', 'string');
        }
    }

    // throws an exception if the given key is set to a class that doesn't implement the given interface
    private function checkOptionClassImplements($key, $interface)
    {
        if (($class = $this->$key)
            && !array_key_exists('Airbrake\\'.$interface, class_implements($class)))
        {
            throw new AirbrakeException("$key $class does not implement $interface");
        }
    }

    // throws an exception if the given key is not set to a valid AirbrakeCallback, plus ensures the right default values and expected class & type
    private function checkAndSetAirbrakeCallback($key, $defaultReturnValue, $shouldReturnType = null, $shouldReturnClass = null)
    {
        if ($object = $this->$key) {
            if (!(is_object($object) && $object instanceof AirbrakeCallback)) {
                throw new AirbrakeException("$key is not an instance of AirbrakeCallback!");
            }
            $object->setDefaultReturnValue($defaultReturnValue);
            $object->setExpectedType($shouldReturnType);
            $object->setExpectedClass($shouldReturnClass);
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

    public function isScalarBlackListed($arg)
    {
        $arg = (string) $arg;
        $scalarBlackList = $this->blacklistedScalarArgsCallback ? $this->blacklistedScalarArgsCallback->call() : array();
        if (in_array($arg, $scalarBlackList)) {
            return true;
        }
        $regexBlackList  = $this->blacklistedRegexArgsCallback ? $this->blacklistedRegexArgsCallback->call() : array();
        foreach ($regexBlackList as $regex) {
            if (preg_match($regex, $arg)) {
                return true;
            }
        }
        return false;
    }

    public static function getInstance()
    {
        return self::$instance;
    }
}
