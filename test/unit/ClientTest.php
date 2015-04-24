<?php
namespace Airbrake;

require_once(__DIR__.'/../../src/Airbrake/Client.php');

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var object
     */
    protected $object;

    protected function setUp() {
        //Mock the Client's notify method, no args, skip __construct
        $this->object = $this->getMock('Airbrake\Client',array('notify'),array(),'',false);
        $this->object->expects($this->once())
                     ->method('notify')
                     ->will($this->returnValue(true));
    }

    /**
     * @covers Airbrake\Client::notifyOnError
     * Assert that calling notifyOnError with a message
     * invokes the notify (sender) method
     */
    public function testNotifyOnError() {
        $message = 'Some Error';
        $this->assertEquals(true, $this->object->notifyOnError($message));
    }

    /**
     * @covers Airbrake\Client::notifyOnException
     * Assert that calling notifyOnException with an Exception
     * invokes the notify (sender) method
     */
    public function testNotifyOnException() {
        $exception = new Exception('Boom');
        $this->assertEquals(true, $this->object->notifyOnException($exception));
    }
}
