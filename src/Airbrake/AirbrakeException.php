<?php
namespace Airbrake;

/**
 * Airbrake exception.
 *
 * @package		Airbrake
 * @author		Drew Butler <drew@abstracting.me>
 * @copyright	(c) 2011 Drew Butler
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class AirbrakeException extends \Exception
{
    // a string to put in an email subject line
    private $shortDescription = null;

    public function setShortDescription($s)
    {
        $this->shortDescription = $s;
    }

    public function getShortDescription()
    {
        return $this->shortDescription;
    }

}
