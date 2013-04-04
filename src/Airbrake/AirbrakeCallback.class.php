<?php
namespace Airbrake;

/**
 * Airbrake callback. A simple class that mainly adds the possibility to cache the callback's result,
 * plus can make sure the callback returns a given type and/or class, and protect against uncaught exceptions
 *
 * @package        Airbrake
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class AirbrakeCallback
{
    private
        $callback,
        $prependedArguments,
        $cachable,
        $cachedResult,
        $computed,
        $shouldReturnType,
        $shouldReturnClass,
        $defaultReturnValue;

    public function __construct($callback, $cachable = false, array $prependedArguments = array())
    {
        $this->callback           = $callback;
        $this->prependedArguments = $prependedArguments;
        $this->cachable           = $cachable;
        $this->reset();
    }

    public function call(array $additionalArugments = array())
    {
        if ($this->cachable && $this->computed) {
            return $this->cachedResult;
        }
        try {
            if (!is_callable($this->callback, false, $callableName)) {
                throw new \Exception(var_export($this->callback, true).' is not a valid callback!');
            }
            // call it, and keep the result if it's an array
            $args   = array_merge($this->prependedArguments, $additionalArugments);
            $result = call_user_func_array($this->callback, $args);
            // check type
            if ($this->shouldReturnType && gettype($result) != $this->shouldReturnType) {
                throw new \Exception('Callback must return a type '.$this->shouldReturnType.'! '.$callableName.' returned a type '.gettype($result).' :\n'.var_export($result, true));
            }
            // check class
            if ($this->shouldReturnClass) {
                if (!is_object($result)) {
                    throw new \Exception('Callback must return an object! '.$callableName.' returned a type '.gettype($result).' :\n'.var_export($result, true));
                }
                if (get_class($result) != $this->shouldReturnClass) {
                    throw new \Exception('Callback must return an instance of '.$this->shouldReturnClass.'! '.$callableName.' returned an instance of '.get_class($result).' :\n'.var_export($result, true));
                }
            }
        } catch (\Exception $e) {
            // notify the upper layer, but keep reporting the current error anyway
            if ($config = Configuration::getInstance()) {
                $config->notifyUpperLayer($e);
            }
            $result = $this->defaultReturnValue;
        }
        if ($this->cachable) {
            $this->cachedResult = $result;
            $this->computed     = true;
        }
        return $result;
    }

    public function setDefaultReturnValue($value)
    {
        $this->defaultReturnValue = $value;
    }

    public function setExpectedType($type)
    {
        $this->shouldReturnType = $type;
    }

    public function setExpectedClass($class)
    {
        $this->shouldReturnClass = $class;
    }

    public function reset()
    {
        $this->computed     = false;
        $this->cachedResult = null;
    }
}
