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
    // a string to put in an email subject line
    private $shortDescription = null;
    // a tag to log errors
    private $logNamespace     = null;

    public function __construct($message = '', $shortDescription = null, $logNamespace = null)
    {
        parent::__construct($message);
        $this->setShortDescription($shortDescription);
        $this->setLogNamespace($logNamespace);
    }

    public function setShortDescription($descr)
    {
        $this->shortDescription = $descr;
    }

    public function getShortDescription()
    {
        return $this->shortDescription ? : 'Airbrake error';
    }

    public function setLogNamespace($ns)
    {
        $this->logNamespace = $ns;
    }

    public function getLogNamespace()
    {
        return $this->logNamespace ? : 'airbrake_default_log_namespace';
    }

}
