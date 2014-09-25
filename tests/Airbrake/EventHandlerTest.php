<?php

namespace Airbrake;

use Airbrake\Stub\TestConnection;
use Airbrake\Helper\TriggerErrors;

include_once __DIR__ . '/../stubs/TestConnection.php';
include_once __DIR__ . '/../helpers/TriggerErrors.php';

class EventHandlerTest extends \PHPUnit_Framework_TestCase
{
    private $handler;
    private $connection;
    private $iniSetting;
    /** @var TriggerErrors */
    private $triggerErrors;

    public function setUp()
    {
        $this->iniSetting = ini_get('error_reporting');
        $this->triggerErrors = new TriggerErrors();
        error_reporting(-1);
    }

    public function tearDown()
    {
        ini_set('error_reporting', $this->iniSetting);
    }

    private function createHandler($options = array(), $notifyOnWarning = true)
    {
        EventHandler::reset();
        $this->connection = new TestConnection();
        $this->handler = EventHandler::start(1, $notifyOnWarning, $options);
        $this->handler->getClient()->setConnection($this->connection);
    }

    public function testSendError()
    {
        $this->createHandler();
        $this->triggerErrors->triggerWarning();
        $this->assertEquals(1, $this->connection->sendCalls);
    }

    public function testErrorFilterApplied()
    {
        $config = array('errorReportingLevel' => E_WARNING | E_NOTICE);
        $this->createHandler($config);

        $this->triggerErrors->triggerWarning();
        $this->triggerErrors->triggerNotice();
        $this->triggerErrors->triggerStrict();

        $this->assertEquals(2, $this->connection->sendCalls);
    }

    public function testNotifyOnWarningMigration()
    {
        //Test that moving the notifyOnWarning behaviour to its own class hasn't
        //introduced any problems
        $this->createHandler(array(), false);
        $this->triggerErrors->triggerNotice();
        $this->triggerErrors->triggerDeprecated();
        $this->triggerErrors->triggerWarning();
        $this->triggerErrors->triggerStrict();
        $this->triggerErrors->triggerUserWarning();
        $this->triggerErrors->triggerUserNotice();
        $this->triggerErrors->triggerRecoverableError();
        $this->assertEquals(0, $this->connection->send_calls);
    }
}
