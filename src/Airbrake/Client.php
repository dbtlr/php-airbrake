<?php
namespace Airbrake;

use Exception;
use Airbrake\Connection\ConnectionInterface;

/**
 * Airbrake client class.
 *
 * @package    Airbrake
 * @author     Drew Butler <drew@dbtlr.com>
 * @copyright  (c) 2011-2013 Drew Butler
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
class Client
{
    /** @var Configuration|null  */
    protected $configuration = null;

    /** @var Connection|null  */
    protected $connection = null;

    /** @var null */
    protected $notice = null;

    /**
     * Build the Client with the Airbrake Configuration.
     *
     * @throws Exception
     * @param Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $configuration->verify();

        $this->configuration = $configuration;
        $this->connection    = new Connection($configuration);
    }

    /**
     * Override the default Connection
     *
     * @throws Exception
     * @param ConnectionInterface $connection
     * @return self
     */
    public function setConnection(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Notify on an error message.
     *
     * @param string $message
     * @param array $backtrace
     * @return string
     */
    public function notifyOnError($message, array $backtrace = null)
    {
        if (!$backtrace) {
            $backtrace = debug_backtrace();
            if (count($backtrace) > 1) {
                array_shift($backtrace);
            }
        }

        $notice = new Notice;
        $notice->load(array(
            'errorClass'   => 'PHP Error',
            'backtrace'    => $backtrace,
            'errorMessage' => $message,
        ));

        return $this->notify($notice);
    }

    /**
     * Notify on an exception
     *
     * @param Exception $exception
     * @return string
     */
    public function notifyOnException(Exception $exception)
    {
        $notice = new Notice;
        $notice->load(array(
            'errorClass'   => get_class($exception),
            'backtrace'    => $this->cleanBacktrace($exception->getTrace() ?: debug_backtrace()),
            'errorMessage' => $exception->getMessage().' in '.$exception->getFile().' on line '.$exception->getLine(),
        ));

        return $this->notify($notice);
    }

    /**
     * Notify about the notice.
     *
     * @param Notice $notice
     * @return string|bool
     */
    public function notify(Notice $notice)
    {
        return $this->connection->send($notice);
    }

    /**
     * Clean the backtrace of unneeded junk.
     *
     * @param array $backtrace
     * @return array
     */
    protected function cleanBacktrace($backtrace)
    {
        foreach ($backtrace as &$item) {
            unset($item['args']);
        }

        return $backtrace;
    }
}
