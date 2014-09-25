<?php

class TestConnection implements Airbrake\Connection\ConnectionInterface
{
  public $send_calls = 0;

  public function send(Airbrake\Notice $notice)
  {
    $this->send_calls ++;
    return true;
  }
}
