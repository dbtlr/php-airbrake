<?php

include_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/stubs/Client.php';
include_once __DIR__ . '/helpers/TriggerErrors.php';

use Airbrake\EventFilter as Filter;
use Airbrake\Configuration as Configuration;

class TriggerErrorsTest extends PHPUnit_Framework_TestCase
{
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

  /**
   * @dataProvider testMethodsProvider
   */
  public function testMethods($expected_level, $method_name)
  {
    Airbrake\EventHandler::reset();
    $connection = new TestConnection();
    $options = array('errorReportingLevel' => $expected_level);
    $handler = Airbrake\EventHandler::start(1, true, $options);
    $handler->getClient()->setConnection($connection);

    $this->h->$method_name();

    $this->assertEquals(1, $connection->send_calls);
  }

  public function testMethodsProvider()
  {
    return array(
      array(E_STRICT, 'triggerStrict'),
      array(E_NOTICE, 'triggerNotice'),
      array(E_WARNING, 'triggerWarning'),
      array(E_USER_WARNING, 'triggerUserWarning'),
      array(E_USER_NOTICE, 'triggerUserNotice'),
      array(E_DEPRECATED, 'triggerDeprecated'),
      //The triggers for these two don't works, unfortunately
      //array(E_COMPILE_WARNING, 'triggerCompileWarning'),
      //array(E_CORE_WARNING, 'triggerCoreWarning'),
      array(E_RECOVERABLE_ERROR, 'triggerRecoverableError'),
    );
  }
}
