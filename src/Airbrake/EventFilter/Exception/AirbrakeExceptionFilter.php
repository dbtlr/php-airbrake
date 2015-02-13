<?php

namespace Airbrake\EventFilter\Exception;

use Airbrake\Exception as AirbrakeException;

/**
 * Filters out Airbrake's internal exceptions so they aren't sent via Airbrake
 * (an exception thrown in the exception handler could be a bad thing)
 *
 * @package    Airbrake
 * @author     Leon Szkliniarz <leon@llamadigital.net>
 * @copyright  (c) 2014 Leon Szkliniarz
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
class AirbrakeExceptionFilter implements FilterInterface
{
    /**
     * Filters out uncaught exceptions before they get sent.
     *
     * @param \Exception $exception
     * @return bool
     */
    public function shouldSendException(\Exception $exception)
    {
        return !($exception instanceof AirbrakeException);
    }
}
