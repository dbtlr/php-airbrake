<?php
namespace Airbrake;

/**
 * Airbrake exception.
 *
 * @package        Airbrake
 * @author         Drew Butler <drew@abstracting.me>
 * @copyright      (c) 2011 Drew Butler
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class AirbrakeException extends \Exception
{
    // a string to put in an email subject line, typically
    private $shortDescription = null;

    public function __construct($message = '', $shortDescription = null, $logNamespace = null)
    {
        parent::__construct($message);
        $this->setShortDescription($shortDescription);
    }

    public function setShortDescription($descr)
    {
        $this->shortDescription = $descr;
    }

    public function getShortDescription()
    {
        return $this->shortDescription ? : 'Airbrake error';
    }
}
