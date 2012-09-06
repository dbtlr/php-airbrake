<?php
namespace Airbrake;

require_once realpath(__DIR__.'/Record.class.php');
require_once realpath(__DIR__.'/Configuration.class.php');
require_once realpath(__DIR__.'/Connection.class.php');
require_once realpath(__DIR__.'/Version.class.php');
require_once realpath(__DIR__.'/AirbrakeException.class.php');
require_once realpath(__DIR__.'/Notice.class.php');
require_once realpath(__DIR__.'/Resque/NotifyJob.php');

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
    protected $configuration = null;
    protected $connection = null;
    protected $notice = null;

    /**
     * Build the Client with the Airbrake Configuration.
     *
     * @throws Airbrake\AirbrakeException
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $configuration->verify();

        $this->configuration = $configuration;
        $this->connection    = new Connection($configuration);
    }

    /**
     * Notify on an error message.
     *
     * @param string $message
     * @param array $backtrace
	 * @return string
     */
    public function notifyOnError($message, $file, $line, array $backtrace = null)
    {
        // add the actual file/line # of the error as the first item of the backtrace
        $backtraceFirstLine = array(
            array(
                'file' => $file,
                'line' => $line
            )
        );

        if (!$backtrace) {
            $backtrace = debug_backtrace();
            if (count($backtrace) > 1) {
                array_shift($backtrace);
            }
        }

        $notice = new Notice;
        $notice->load(array(
            'errorClass'   => 'PHP Error',
            'backtrace'    => array_merge($backtraceFirstLine, $backtrace),
            'errorMessage' => $message,
        ));

        return $this->notify($notice);
    }

    /**
     * Notify on an exception
     *
     * @param Airbrake\Notice $notice
	 * @return string
     */
    public function notifyOnException(\Exception $exception)
    {
        $notice = new Notice;

        // add the actual file/line # of the error as the first item of the backtrace
        $backtrace = array(
            array(
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            )
        );

        $backtrace = array_merge($backtrace,
            $exception->getTrace() ?: debug_backtrace());
        $notice->load(array(
            'errorClass'   => get_class($exception),
            'backtrace'    => $backtrace,
            'errorMessage' => 'Uncaught '.get_class($exception).' : '.$exception->getMessage(),
        ));

        return $this->notify($notice);
    }

    /**
     * Notify about the notice.
     *
     * If there is a PHP Resque client given in the configuration, then use that to queue up a job to
     * send this out later. This should help speed up operations.
     *
     * @param Airbrake\Notice $notice
     */
    public function notify(Notice $notice)
    {
        if (class_exists('Resque') && $this->configuration->queue) {
            //print_r($notice);exit;
            $data = array('notice' => serialize($notice), 'configuration' => serialize($this->configuration));
            \Resque::enqueue($this->configuration->queue, 'Airbrake\\Resque\\NotifyJob', $data);
            return;
        }

        return $this->connection->send($notice);
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }
}