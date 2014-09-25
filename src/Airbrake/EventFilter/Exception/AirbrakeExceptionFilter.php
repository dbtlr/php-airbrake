<?php

namespace Airbrake\EventFilter\Exception;

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
   * @param Exception $exception
   * @see http://us3.php.net/manual/en/function.set-exception-handler.php
   * @return bool
   */
  public function shouldSendException(\Exception $e)
  {
    return !($e instanceof Airbrake\Exception);
  }
}
