<?php
namespace Airbrake\Resque;

use Airbrake\Connection;

class NotifyJob
{
    public function perform()
    {
        $notice = unserialize($this->args['notice']);
        $configuration = unserialize($this->args['configuration']);

        $connection = new Connection($configuration);
        echo $connection->send($notice);
    }
}
