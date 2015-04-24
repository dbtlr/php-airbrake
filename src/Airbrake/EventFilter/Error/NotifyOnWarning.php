<?php

namespace Airbrake\EventFilter\Error;

class NotifyOnWarning implements FilterInterface
{
    /** @var array  */
    protected static $warningErrors = array(
        \E_NOTICE            => 'Notice',
        \E_STRICT            => 'Strict',
        \E_USER_WARNING      => 'User Warning',
        \E_USER_NOTICE       => 'User Notice',
        \E_DEPRECATED        => 'Deprecated',
        \E_WARNING           => 'Warning',
        \E_USER_DEPRECATED   => 'User Deprecated',
        \E_CORE_WARNING      => 'Core Warning',
        \E_COMPILE_WARNING   => 'Compile Warning',
        \E_RECOVERABLE_ERROR => 'Recoverable Error'
    );

    /**
     * Filters out PHP errors before they get sent
     *
     * @param int $type
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @see http://us3.php.net/manual/en/function.set-error-handler.php
     * @return bool
     */
    public function shouldSendError($type, $message, $file = null, $line = null, $context = null)
    {
        return !array_key_exists($type, self::$warningErrors);
    }
}
