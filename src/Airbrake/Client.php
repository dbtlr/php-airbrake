<?php
namespace Airbrake;

use Exception;

require_once 'Record.php';
require_once 'Configuration.php';
require_once 'Connection.php';
require_once 'Version.php';
require_once 'Exception.php';
require_once 'Notice.php';

/**
 * Airbrake client class.
 *
 * @package		Airbrake
 * @author		Drew Butler <drew@abstracting.me>
 * @copyright	(c) 2011 Drew Butler
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Client
{
    protected $connection = null;
    protected $notice = null;

    /**
     * Build the Client with the Airbrake Configuration.
     *
     * @throws Airbrake\Exception
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $configuration->verify();

        $this->connection = new Connection($configuration);       
    }
    
    /**
     * Notify on an error message.
     *
     * @param string $message
     */
    public function notifyOnError($message)
    {
        $backtrace = debug_backtrace();
        if (count($backtrace) > 1) {
            array_shift($backtrace);
        }
        
        $notice = new Notice;
        $notice->load(array(
            'errorClass'   => 'PHP Error',
            'backtrace'    => $backtrace,
            'errorMessage' => $message,
        ));

        return $this->connection->send($notice);
    }
    
    /**
     * Notify on an exception
     *
     * @param Airbrake\Notice $notice
     */
    public function notifyOnException(Exception $exception)
    {
        $notice = new Notice;
        $notice->load(array(
            'exception'    => $exception,
            'errorClass'   => get_class($exception),
            'backtrace'    => $exception->getTrace() ?: debug_backtrace(),
            'errorMessage' => $exception->getMessage(),
        ));

        return $this->connection->send($notice);
    }
    

}