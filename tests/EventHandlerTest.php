<?php

include_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/stubs/Client.php';
include_once __DIR__ . '/helpers/TriggerErrors.php';

use Airbrake\EventFilter as Filter;
use Airbrake\Configuration as Configuration;

class EventHandlerTest extends PHPUnit_Framework_TestCase
{
  private $handler;
  private $connection;
  private $ini_setting;
  private $h;

  public function setUp()
  {
    $this->ini_setting = ini_get('error_reporting');
    $this->h = new TriggerErrors();
    error_reporting(-1);
  }
  public function tearDown()
  {
    ini_set('error_reporting', $this->ini_setting);
  }

  private function createHandler($options = array(), $notifyOnWarning = true)
  {
    Airbrake\EventHandler::reset();
    $this->connection = new TestConnection();
    $this->handler = Airbrake\EventHandler::start(1, $notifyOnWarning, $options);
    $this->handler->getClient()->setConnection($this->connection);
  }

  public function testSendError()
  {
    $this->createHandler();
    $this->h->triggerWarning();
    $this->assertEquals(1, $this->connection->send_calls);
  }

  public function testErrorFilterApplied()
  {
    $config = array('errorReportingLevel' => E_WARNING | E_NOTICE);
    $this->createHandler($config);

    $this->h->triggerWarning();
    $this->h->triggerNotice();
    $this->h->triggerStrict();

    $this->assertEquals(2, $this->connection->send_calls);
  }

  public function testNotifyOnWarningMigration()
  {
    //Test that moving the notifyOnWarning behaviour to its own class hasn't 
    //introduced any problems
    $this->createHandler(array(), false);
    $this->h->triggerNotice();
    $this->h->triggerDeprecated();
    $this->h->triggerWarning();
    $this->h->triggerStrict();
    $this->h->triggerUserWarning();
    $this->h->triggerUserNotice();
    $this->h->triggerRecoverableError();
    $this->assertEquals(0, $this->connection->send_calls);
  }
}
