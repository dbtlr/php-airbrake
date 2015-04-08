<?php

namespace Airbrake;

use Airbrake\Stub\TestConnection;
use Airbrake\Helper\TriggerErrors;

include_once __DIR__ . '/../stubs/TestConnection.php';
include_once __DIR__ . '/../helpers/TriggerErrors.php';

class TriggerErrorsTest extends \PHPUnit_Framework_TestCase
{
    protected $iniSetting;
    protected $trigger;

    public function setUp()
    {
        $this->iniSetting = ini_get('error_reporting');
        $this->trigger = new TriggerErrors();
        error_reporting(-1);
    }

    public function tearDown()
    {
        ini_set('error_reporting', $this->iniSetting);
    }

    /**
     * @dataProvider testMethodsProvider
     */
    public function testMethods($expectedLevel, $methodName)
    {
        EventHandler::reset();
        $connection = new TestConnection();
        $options = array('errorReportingLevel' => $expectedLevel);
        $handler = EventHandler::start(1, true, $options);
        $handler->getClient()->setConnection($connection);

        $this->trigger->$methodName();

        $this->assertEquals(1, $connection->sendCalls);
    }

    public function testMethodsProvider()
    {
        $data = array(
            array(E_NOTICE, 'triggerNotice'),
            array(E_WARNING, 'triggerWarning'),
            array(E_USER_WARNING, 'triggerUserWarning'),
            array(E_USER_NOTICE, 'triggerUserNotice'),
            //The triggers for these two don't works, unfortunately
            //array(E_COMPILE_WARNING, 'triggerCompileWarning'),
            //array(E_CORE_WARNING, 'triggerCoreWarning'),
            array(E_RECOVERABLE_ERROR, 'triggerRecoverableError'),
        );

        if (!defined('HHVM_VERSION')) {
            // HHVM can not trigger these errors for testing purposes.
            $data[] = array(E_STRICT, 'triggerStrict');
            $data[] = array(E_DEPRECATED, 'triggerDeprecated');
        }

        return $data;
    }
}
