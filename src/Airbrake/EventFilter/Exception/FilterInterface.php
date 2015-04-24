<?php

namespace Airbrake\EventFilter\Exception;

/**
 * Interface for Airbrake exception filters. These are used to filter out
 * uncaught exceptions. If you want to filter native PHP errors, please see
 * Airbrake\EventFilter\Error\FilterInterface instead
 *
 * @package    Airbrake
 * @author     Leon Szkliniarz <leon@llamadigital.net>
 * @copyright  (c) 2014 Leon Szkliniarz
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
interface FilterInterface
{
    /**
     * @param \Exception $exception
     * @return mixed
     */
    public function shouldSendException(\Exception $exception);
}
