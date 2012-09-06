<?php
namespace Airbrake;

use Airbrake\AirbrakeException as AirbrakeException;

require_once 'Client.class.php';
require_once 'Configuration.class.php';

class InvalidHashException extends \Exception {}

/**
 * Airbrake EventHandler class.
 *
 * @package		Airbrake
 * @author		Drew Butler <drew@abstracting.me>
 * @copyright	(c) 2011 Drew Butler
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class EventHandler
{
    /**
     * The singleton instance
     */
    protected static $instance = null;
    protected $airbrakeClient = null;
    protected $notifyOnWarning = null;
    protected $configuration = null;

    protected $errorNames = array ( \E_NOTICE            => 'Notice',
                                    \E_STRICT            => 'Strict',
                                    \E_USER_WARNING      => 'User Warning',
                                    \E_USER_NOTICE       => 'User Notice',
                                    \E_DEPRECATED        => 'Deprecated',
                                    \E_USER_DEPRECATED   => 'User Deprecated',
                                    \E_CORE_WARNING      => 'Core Warning',
                                    \E_ERROR             => 'Error',
                                    \E_PARSE             => 'Parse',
                                    \E_COMPILE_WARNING   => 'Compile Warning',
                                    \E_COMPILE_ERROR     => 'Compile Error',
                                    \E_CORE_ERROR        => 'Core Error',
                                    \E_WARNING           => 'Warning',
                                    \E_USER_ERROR        => 'User Error',
                                    \E_RECOVERABLE_ERROR => 'Recoverable Error' );

    // pointers to previous handler
    private static $previousExceptionHandler = null;
    // an array containing the hashes of the handled errors to avoid reporting them again on shutdown
    private $handledErrors;

    /**
     * Build with the Airbrake client class.
     *
     * @param Airbrake\Client $client
     */
    public function __construct(Client $client, $notifyOnWarning)
    {
        $this->notifyOnWarning = $notifyOnWarning;
        $this->airbrakeClient = $client;

        $this->handledErrors = array();
    }

    /**
     * Get the current handler.
     *
     * @param string $apiKey
     * @param bool $notifyOnWarning
     * @param array $options
     * @return EventHandler
     */
    public static function start($apiKey, $notifyOnWarning=false, array $options=array())
    {
        if ( !isset(self::$instance)) {
            $config = new Configuration($apiKey, $options);

            $client = new Client($config);
            self::$instance = new self($client, $notifyOnWarning);
            self::$instance->configuration = $config;

            $seamless = $config->get('handleSeamlessly');

            // errors - handle them all!
            set_error_handler(array(self::$instance, 'onError'), E_ALL);

            // exceptions
            if ($seamless) {
                self::$previousExceptionHandler = set_exception_handler(function(\Exception $exception) {
                    // log into airbrake
                    call_user_func_array(array(EventHandler::getInstance(), 'onException'),
                        array($exception));

                    if (!property_exists($exception, 'airbrakeDontRethrow')) {
                        // then call the original handler (and we disable Airbrake fatal error handler before to avoid getting twice the same error logged in there)
                        EventHandler::reset(false);
                        throw $exception;
                    }
                });
            } else {
                // catch everything, don't re-throw
                set_exception_handler(array(self::$instance, 'onException'));
            }

            // fatal errors
            register_shutdown_function(array(self::$instance, 'onShutdown'));
        }

        return self::$instance;
    }


    /**
     * Revert the handlers back to their original state.
     */
    public static function reset($restore = true)
    {
        if (isset(self::$instance) && $restore) {
            restore_error_handler();
            restore_exception_handler();
        }

        self::$instance = null;
    }

    /**
     * Catches standard PHP style errors
     *
     * @see http://us3.php.net/manual/en/function.set-error-handler.php
     * @param int $type
     * @param string $message
     * @param string $file
     * @param string $line
     * @param array $context
     * @return bool
     */
    public function onError($type, $message, $file = null, $line = null, $context = null)
    {
        // remember this error
        try {
            $this->handledErrors[$this->hashError($type, $message, $file, $line)] = true;
        }
        catch (InvalidHashException $e) {}

        // This will catch silenced @ function calls and keep them quiet.
        if (ini_get('error_reporting') == 0) {
            return true;
        }

        // if the seamless mode is activated, return false to let the error bubble up
        // otherwise, return true to stop it here (see set_error_handler doc)
        // return false;
        $result = !($this->configuration && $this->configuration->get('handleSeamlessly'));

        // check whether we want to report this error
        if ($this->configuration && $this->configuration->exists('errorReporting') &&
            !($this->configuration->get('errorReporting') & $type))
        {
            return $result;
        }

        $backtrace = debug_backtrace();
        array_shift( $backtrace );

        $message = sprintf('A PHP error occurred (%s). %s', $this->errorNames[$type], $message);
        $this->airbrakeClient->notifyOnError($message, $file, $line, $backtrace);

        return $result;
    }


    /**
     * Catches uncaught exceptions.
     *
     * @see http://us3.php.net/manual/en/function.set-exception-handler.php
     * @param Exception $exception
     * @return bool
     */
    public function onException(\Exception $exception)
    {
        $this->airbrakeClient->notifyOnException($exception);

        return true;
    }

    /**
     * Handles the PHP shutdown event.
     *
     * This event exists almost solely to provide a means to catch and log errors that might have been
     * otherwise lost when PHP decided to die unexpectedly.
     */
    public function onShutdown()
    {
        // If the instance was unset, then we shouldn't run.
        if (self::$instance == null) {
            return;
        }

        // This will help prevent multiple calls to this, in case the shutdown handler was declared
        // multiple times. This should only occur in unit tests, when the handlers are created
        // and removed repeatedly. As we cannot remove shutdown handlers, this prevents us from
        // calling it 1000 times at the end.
        self::$instance = null;

        $error = error_get_last();

        // check if there is an error, if we report it, and if we haven't reported it yet!
        try {
            if (!$error || !($error['type'] & error_reporting())
                || array_key_exists($this->hashError($error['type'], $error['message'], $error['file'], $error['line']), $this->handledErrors)) {
                return;
            }
        }
        catch (InvalidHashException $e) {}

        $this->airbrakeClient->notifyOnError(
            sprintf(
                'Unexpected shutdown. Error: %s  File: %s  Line: %d',
                $error['message'], $error['file'], $error['line']),
            $error['file'], $error['line']);
    }

    public static function getClient()
    {
        if (self::$instance == null) {
            return null;
        }
        return self::$instance->airbrakeClient;
    }

    public static function getPreviousExceptionHandler()
    {
        return self::$previousExceptionHandler;
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    private function hashError($type, $message, $file, $line)
    {
        $hashedString = (string)$type;
        $valid = false;
        if (is_string($message) && strlen($message)) {
            $valid = true;
            $hashedString .= $message;
        }
        if (is_string($file) && strlen($file) > 0
            && is_int($line) && $line > 0)
        {
            $valid = true;
            $hashedString .= $file.$line;
        }
        if (!$valid) {
            throw new InvalidHashException();
        }
        return hash('md5', $hashedString);
    }

}