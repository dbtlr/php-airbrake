<?php

namespace Airbrake\EventFilter\Error;

use Airbrake\Configuration;

/**
 * Duplicates the functionality you normally get via the error_reporting php.ini
 * setting. Unfortunately, this setting does not apply to custom php error
 * handlers and it is the handler's job to filter out errors.
 *
 * @package    Airbrake
 * @author     Leon Szkliniarz <leon@llamadigital.net>
 * @copyright  (c) 2014 Leon Szkliniarz
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
class ErrorReporting implements FilterInterface
{
    /** @var \Airbrake\Configuration  */
    private $config;

    /**
     * @param Configuration $config
     */
    public function __construct(Configuration $config)
    {
        $this->config = $config;
    }

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
        $level = $this->config->get('errorReportingLevel');
        if (-1 == $level) {
            return true;
        }

        return $level & $type;
    }
}
