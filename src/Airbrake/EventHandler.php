<?php
namespace Airbrake;

use Exception;

require_once 'Client.php';
require_once 'Configuration.php';

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

    protected $warningErrors = array ( \E_NOTICE          => 'Notice',
                                       \E_STRICT          => 'Strict',
                                       \E_USER_WARNING    => 'User Warning',
                                       \E_USER_NOTICE     => 'User Notice',
                                       \E_DEPRECATED      => 'Deprecated',
                                       \E_USER_DEPRECATED => 'User Deprecated',
                                       \E_CORE_WARNING    => 'Core Warning' );

    protected $fatalErrors = array ( \E_ERROR             => 'Error',
                                     \E_PARSE             => 'Parse',
                                     \E_COMPILE_WARNING   => 'Compile Warning',
                                     \E_COMPILE_ERROR     => 'Compile Error',
                                     \E_CORE_ERROR        => 'Core Error',
                                     \E_WARNING           => 'Warning',
                                     \E_USER_ERROR        => 'User Error',
                                     \E_RECOVERABLE_ERROR => 'Recoverable Error' );
    // pointers to previous handler
    private static $previousExceptionHandler = null;

    /**
     * Build with the Airbrake client class.
     *
     * @param Airbrake\Client $client
     */
    public function __construct(Client $client, $notifyOnWarning)
    {
        $this->notifyOnWarning = $notifyOnWarning;
        $this->airbrakeClient = $client;
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

            $transparent = $config->get('handleTransparently');

            // errors
            error_reporting(E_ALL);
            set_error_handler(array(self::$instance, 'onError'), E_ALL);

            // exceptions
            if ($transparent) {
                self::$previousExceptionHandler = set_exception_handler(function($exception) {
                    // log into airbrake
                    call_user_func_array(array(EventHandler::getInstance(), 'onException'),
                        array($exception));

                    // then call the original handler (and we disable Airbrake fatal error handler before to avoid getting twice the same error logged in there)
                    EventHandler::reset(false);
                    throw $exception;
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
        // This will catch silenced @ function calls and keep them quiet.
        if (ini_get('error_reporting') == 0) {
            return true;
        }

        $backtrace = debug_backtrace();
        array_shift( $backtrace );

        if (isset($this->fatalErrors[$type])) {
            throw new Exception(sprintf('A PHP error occurred (%s). %s', $this->fatalErrors[$type], $message));
        }

        if ($this->notifyOnWarning && isset ( $this->warningErrors[$type])) {
            $message = sprintf('A PHP earning occurred (%s). %s', $this->warningErrors[$type], $message);
            $this->airbrakeClient->notifyOnError($message);
            return true;
        }

        return true;
    }


    /**
     * Catches uncaught exceptions.
     *
     * @see http://us3.php.net/manual/en/function.set-exception-handler.php
     * @param Exception $exception
     * @return bool
     */
    public function onException(Exception $exception)
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

        if (!$error || !($error['type'] & error_reporting())) {
            // There was no last error, or it is not of a type that we report
            return;
        }

        $this->airbrakeClient->notifyOnError(
            sprintf(
                'Unexpected shutdown. Error: %s  File: %s  Line: %d',
                $error['message'], $error['file'], $error['line']));
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

}
