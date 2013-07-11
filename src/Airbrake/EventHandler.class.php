<?php
namespace Airbrake;

use Airbrake\AirbrakeException as AirbrakeException;

require_once 'Client.class.php';
require_once 'Configuration.class.php';

class InvalidHashException extends \Exception {}

/**
 * Airbrake EventHandler class.
 *
 * @package        Airbrake
 * @author         Drew Butler <drew@abstracting.me>
 * @copyright      (c) 2011 Drew Butler
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class EventHandler
{
    /**
     * The singleton instance
     */
    protected static $instance = null;

    protected $airbrakeClient  = null;
    protected $notifyOnWarning = null;
    protected $configuration   = null;

    // We want to avoid creating infinite loops when reporting,
    // so we'll only accept an 'Airbrake stack' at most that deep
    // (past that level we just ignore incoming errors)
    const MAX_AIRBRAKE_STACK_DEPTH = 2;
    // the current stack depth
    private $currentAirbrakeStackDepth;

    // Minimum amount of memory required to actually report fatal errors to Airbrake
    // useful when reporting "out of memory" errors
    // around 20M should be enough
    private static $memoryAllowedOnShutdown = '40M';

    const NAME  = 'name';
    const LEVEL = 'level';
    private static $errorNamesAndLevels = array (
        \E_NOTICE            => array(self::NAME => 'Notice',            self::LEVEL => 'warning'),
        \E_STRICT            => array(self::NAME => 'Strict',            self::LEVEL => 'warning'),
        \E_USER_WARNING      => array(self::NAME => 'User Warning',      self::LEVEL => 'warning'),
        \E_USER_NOTICE       => array(self::NAME => 'User Notice',       self::LEVEL => 'warning'),
        \E_DEPRECATED        => array(self::NAME => 'Deprecated',        self::LEVEL => 'warning'),
        \E_USER_DEPRECATED   => array(self::NAME => 'User Deprecated',   self::LEVEL => 'warning'),
        \E_CORE_WARNING      => array(self::NAME => 'Core Warning',      self::LEVEL => 'warning'),
        \E_ERROR             => array(self::NAME => 'Error',             self::LEVEL => 'error'),
        \E_PARSE             => array(self::NAME => 'Parse',             self::LEVEL => 'error'),
        \E_COMPILE_WARNING   => array(self::NAME => 'Compile Warning',   self::LEVEL => 'error'),
        \E_COMPILE_ERROR     => array(self::NAME => 'Compile Error',     self::LEVEL => 'error'),
        \E_CORE_ERROR        => array(self::NAME => 'Core Error',        self::LEVEL => 'error'),
        \E_WARNING           => array(self::NAME => 'Warning',           self::LEVEL => 'warning'),
        \E_USER_ERROR        => array(self::NAME => 'User Error',        self::LEVEL => 'fatal'),
        \E_RECOVERABLE_ERROR => array(self::NAME => 'Recoverable Error', self::LEVEL => 'fatal')
);

    // pointers to previous handler
    private static $previousExceptionHandler = null;
    // an array containing the hashes of the handled errors to avoid reporting them again on shutdown
    private $handledErrors;
    // a flag to say if the shutdown function has already been called on that handler or not
    // to prevent infinite loops in the shutdown handler
    private $shutdownCalled;

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
        $this->shutdownCalled = false;
        $this->currentAirbrakeStackDepth = 0;
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
        if (!isset(self::$instance)) {
            $config = new Configuration($apiKey, $options);

            $client = new Client($config);
            self::$instance = new self($client, $notifyOnWarning);
            self::$instance->configuration = $config;

            $seamless = $config->get('handleSeamlessly');

            // errors - handle them all!
            set_error_handler(array(self::$instance, 'onError'), E_ALL);

            // exceptions
            self::$previousExceptionHandler = set_exception_handler(function(\Exception $exception) use($config)
            {
                // log into airbrake
                call_user_func_array(
                    array(EventHandler::getInstance(), 'onException'),
                    array($exception, $config));

                if ($config->get('handleSeamlessly')
                    && !(property_exists($exception, 'airbrakeDontRethrow') && $exception->airbrakeDontRethrow))
                    {
                    // then call the original handler
                    // (and we disable Airbrake fatal error handler before to avoid getting twice the same error logged in there)
                    EventHandler::reset(false);
                    $previousHandler = EventHandler::getPreviousExceptionHandler();
                    if ($previousHandler) {
                        call_user_func_array($previousHandler, array($exception));
                    } else {
                        // no previous handler, just re-throw it
                        throw $exception;
                    }
                }
            });

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

        self::$instance->shutdownCalled = true;
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
        // if the seamless mode is activated, return false to let the error bubble up
        // otherwise, return true to stop it here (see set_error_handler doc)
        // return false;
        $result = !($this->configuration && $this->configuration->get('handleSeamlessly'));

        // prevent infinite loops
        if (!$this->incrementStackTraceDepth()) {
            return $result;
        }

        // remember this error
        try {
            $this->handledErrors[$this->hashError($type, $message, $file, $line)] = true;
        }
        catch (InvalidHashException $e) {}

        // This will catch silenced @ function calls and keep them quiet.
        if (ini_get('error_reporting') == 0) {
            return $result;
        }

        // check whether we want to report this error
        if ($this->configuration && $this->configuration->exists('errorReporting')
            && !($this->configuration->get('errorReporting') & $type))
        {
            return $result;
        }

        $backtrace = debug_backtrace();
        array_shift($backtrace);

        $errorMeta = self::$errorNamesAndLevels[$type];
        $message = sprintf('A PHP error occurred (%s). %s', $errorMeta[self::NAME], $message);

        $this->airbrakeClient->notifyOnError($message, $errorMeta[self::LEVEL], $file, $line, $backtrace);

        $this->decrementStackTraceDepth();

        return $result;
    }


    /**
     * Catches uncaught exceptions.
     *
     * @see http://us3.php.net/manual/en/function.set-exception-handler.php
     * @param \Exception $exception
     * @param[optional] Configuration $config = null
     * @return bool
     */
    public function onException(\Exception $exception, Configuration $config = null)
    {
        // prevent infinite loops
        if (!$this->incrementStackTraceDepth()) {
            return true;
        }

        if ($config && in_array(
            get_class($exception),
            $config->get('silentExceptionClasses'))) {
            // mark it to leave it alone
            $exception->airbrakeDontRethrow = true;
        } else {
            // business as usual
            $this->airbrakeClient->notifyOnException($exception);
        }

        $this->decrementStackTraceDepth();

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
        // This will help prevent multiple calls to this, in case the shutdown handler was declared
        // multiple times. This should only occur in unit tests, when the handlers are created
        // and removed repeatedly. As we cannot remove shutdown handlers, this prevents us from
        // calling it 1000 times at the end.
        if ($this->shutdownCalled) {
            return;
        }
        $this->shutdownCalled = true;

        // try to get some additional memory (useful when reporting "out of memory" errors)
        try {
            if (!ini_set(
                'memory_limit',
                (int) (self::nbBytesStringToInt(self::$memoryAllowedOnShutdown)
                    + self::nbBytesStringToInt(ini_get('memory_limit'))) )) {
                // ini_set failed, just uncap memory altogether
                ini_set('memory_limit', -1);
            }
        } catch(\Exception $e) {
            ini_set('memory_limit', -1);
        }

        $error = error_get_last();

        // check if there is an error, if we report it, and if we haven't reported it yet!
        try {
            if (!$error || !($error['type'] & error_reporting())
                || array_key_exists($this->hashError($error['type'], $error['message'], $error['file'], $error['line']), $this->handledErrors)) {
                return;
            }
        }
        catch (InvalidHashException $e) {}

        $message = sprintf('Unexpected shutdown. Error: %s  File: %s  Line: %d',
                            $error['message'], $error['file'], $error['line']);

        $this->airbrakeClient->notifyOnError($message, 'fatal', $error['file'], $error['line']);
    }

    public static function getClient()
    {
        if (self::$instance === null) {
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

    // increments the stack trace, and returns true iff we should report the error on hand
    private function incrementStackTraceDepth()
    {
        if ($this->currentAirbrakeStackDepth < self::MAX_AIRBRAKE_STACK_DEPTH) {
            $this->currentAirbrakeStackDepth++;
            return true;
        }
        return false;
    }

    private function decrementStackTraceDepth()
    {
        $this->currentAirbrakeStackDepth--;
    }

    // converts a string of the form '10G' or '5T' or '8M' to the corresponding number of bytes
    // TODO: if we need more helper functions around here, we should move them, including this one, to a separate file
    private static function nbBytesStringToInt($s)
    {
        return preg_replace_callback('/^(\d+)\s*(K|M|G|T)*$/i', function($matches) {
            $n = (int) $matches[1];
            if (count($matches) == 3) {
                // i.e. there is a letter in the string
                $u = strtolower($matches[2]);
                switch ($u) {
                    case 't':
                        // PHP does use 1024, not 1000 (see http://www.php.net/manual/en/faq.using.php#faq.using.shorthandbytes)
                        $n *= 1024;
                    case 'g':
                        $n *= 1024;
                    case 'm':
                        $n *= 1024;
                    case 'k':
                        $n *= 1024;
                }
            }
            return $n;
        }, (string) $s);
    }
}
