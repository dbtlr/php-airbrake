<?php
namespace Airbrake\Resque;

require_once realpath(__DIR__.'/../Client.php');

use Airbrake\Connection;

class NotifyJob
{
    public function perform()
    {
        print_r($this->args);
        $notice = unserialize($this->args['notice']);
        $configuration = unserialize($this->args['configuration']);

        $connection = new Connection($configuration);
        $connection->send($notice);
    }
}
