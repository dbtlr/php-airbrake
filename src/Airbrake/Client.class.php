<?php
namespace Airbrake;

require_once realpath(__DIR__.'/Record.class.php');
require_once realpath(__DIR__.'/Configuration.class.php');
require_once realpath(__DIR__.'/Connection.class.php');
require_once realpath(__DIR__.'/Version.class.php');
require_once realpath(__DIR__.'/AirbrakeException.class.php');
require_once realpath(__DIR__.'/Notice.class.php');
require_once realpath(__DIR__.'/IDelayedNotification.php');
require_once realpath(__DIR__.'/Resque/NotifyJob.php');

/**
 * Airbrake client class.
 *
 * @package        Airbrake
 * @author         Drew Butler <drew@abstracting.me>
 * @copyright      (c) 2011 Drew Butler
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class Client
{
    protected $configuration = null;
    protected $connection    = null;
    protected $notice        = null;

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
        if (property_exists($exception, 'airbrakeMessagePrefix') && $exception->airbrakeMessagePrefix) {
            $prefix = $exception->airbrakeMessagePrefix;
        } else {
            $prefix = 'Uncaught';
        }
        if (property_exists($exception, 'airbrakeExceptionClassOverride') && $exception->airbrakeExceptionClassOverride) {
            $classException = $exception->airbrakeExceptionClassOverride;
        } else {
            $classException = get_class($exception);
        }
        $notice->load(array(
            'errorClass'   => $classException,
            'backtrace'    => $backtrace,
            'errorMessage' => $prefix.' '.$classException.' : '.$exception->getMessage(),
        ));

        return $this->notify($notice);
    }

    /**
     * Notify about the notice.
     *
     * If there is a PHP Resque client given in the configuration, then use that to queue up a job to
     * send this out later. This should help speed up operations.
     * If some other class to create a delayed task is provided, we use that.
     * Otherwise, we send it live, in a blocking way.
     *
     * @param Airbrake\Notice $notice
     */
    private function notify(Notice $notice)
    {
        $config = $this->configuration;
        // use Resque, if available
        if (class_exists('Resque') && $config->queue) {
            $data = array('notice' => serialize($notice), 'configuration' => serialize($config));
            \Resque::enqueue($config->queue, 'Airbrake\\Resque\\NotifyJob', $data);
            return;
        }
        // or if another class to notify later has been provided, try to use that
        elseif ($delayedNotifClass = $config->get('delayedNotificationClass')) {
            try {
                if (!$delayedNotifClass::createDelayedNotification(array('Airbrake\Connection', 'notify'),
                        $notice->toXml($config),
                        $config->apiEndPoint,
                        $config->delayedTimeout,
                        Connection::getDefaultHeaders(),
                        $notice->errorMessage,
                        $config->get('errorNotificationCallback')))
                {
                    throw new Exception('Couldn\'t create delayed task');
                }
                return;
            } catch(Exception $e) {
                $config->notifyUpperLayer($e);
            }
        }

        // nothing fancy, we just notify in a blocking way...
        return $this->connection->send($notice);
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }
}
