<?php

namespace Airbrake\EventFilter\Error;

/**
 * Interface for Airbrake error filters. These are used to filter out native PHP
 * errors. If you want to filter uncaught exceptions, please see
 * Airbrake\EventFilter\Exception\FilterInterface instead
 *
 * @package    Airbrake
 * @author     Leon Szkliniarz <leon@llamadigital.net>
 * @copyright  (c) 2014 Leon Szkliniarz
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
interface FilterInterface
{
    /**
     * Filters out PHP errors before they get sent
     *
     * @param int $type
     * @param string $message
     * @param string $file
     * @param int $line
     * @param array $context
     * @return bool
     */
    public function shouldSendError($type, $message, $file = null, $line = null, $context = null);
}
