<?php
namespace Airbrake\Connection;

/**
 * Airbrake connection Interface. Make a class implement this and pass it into 
 * the Airbrake\Client::setConnection() method to add a custom method of sending 
 * Airbrake notices.
 *
 * @package    Airbrake
 * @author     Leon Szkliniarz <leon@llamadigital.net>
 * @copyright  (c) 2014 Leon Szkliniarz
 * @license    http://www.opensource.org/licenses/mit-license.php
 */

interface ConnectionInterface
{
  /**
   * Handles a notice being sent.
   *
   * @param Airbrake\Notice $notice
   * @return boolean success
   */
  public function send(\Airbrake\Notice $notice);
}
