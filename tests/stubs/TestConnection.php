<?php

namespace Airbrake\Stub;

use Airbrake\Notice;
use Airbrake\Connection\ConnectionInterface;

class TestConnection implements ConnectionInterface
{
    /** @var int */
    public $sendCalls = 0;

    /**
     * @param Notice $notice
     * @return bool
     */
    public function send(Notice $notice)
    {
        $this->sendCalls++;
        return true;
    }
}
