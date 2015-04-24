<?php
namespace Airbrake;

require_once(__DIR__.'/../../src/Airbrake/Configuration.php');
require_once(__DIR__.'/../../src/Airbrake/Exception.php');

class ConfigurationTest extends \PHPUnit_Framework_TestCase {
    /**
     * @var Configuration
     */
    protected $object;

    protected function setUp() {
        $config = array(
            'component' => 'home',
            'action' => 'index',
            'projectRoot' => realpath(__DIR__),
            'url' => 'http://airbrake.io/boom',
            'hostname' => 'airbraker',
            'queue' => 'airbrake-queue',
            'getData' => array('get' => 'this'),
            'postData' => array('post' => 'that'),
            'filters' => array('ExceptionOne','ExceptionTwo'),
            'async' => true
        );
        $this->object = new Configuration('1234',$config);
    }

    /**
     * @covers Airbrake\Configuration
     */
    public function testDefaultAttributeValues() {
        $this->assertEquals($this->object->get('timeout'), 2);
        $this->assertEquals($this->object->get('environmentName'), 'production');
        $this->assertEquals($this->object->get('apiEndPoint'), 'http://api.airbrake.io/notifier_api/v2/notices');
    }

    public function testSetAttributesAtRuntime() {
        $this->assertEquals($this->object->get('component'), 'home');
        $this->assertEquals($this->object->get('action'), 'index');
        $this->assertEquals($this->object->get('projectRoot'), realpath(__DIR__));
        $this->assertEquals($this->object->get('url'), 'http://airbrake.io/boom');
        $this->assertEquals($this->object->get('hostname'), 'airbraker');
        $this->assertEquals($this->object->get('queue'), 'airbrake-queue');
        $this->assertEquals($this->object->get('getData'), array('get' => 'this'));
        $this->assertEquals($this->object->get('postData'), array('post' => 'that'));
        $this->assertInternalType('array', $this->object->get('getData'));
        $this->assertInternalType('array', $this->object->get('postData'));
        $this->assertInternalType('array', $this->object->get('filters'));
        $this->assertEquals('ExceptionOne', $this->object->get('filters')[0]);
        $this->assertEquals('ExceptionTwo', $this->object->get('filters')[1]);
        $this->assertEquals(true, $this->object->get('async'));
    }

    /**
     * @covers Airbrake\Configuration::getParamters
     */
    public function testGetParamters() {
        $this->assertEquals($this->object->getParameters(), array(
            'get' => 'this',
            'post' => 'that')
        );
    }

    /**
     * @covers Airbrake\Configuration::verify
     */
    public function testVerify() {
        $this->object->set('apiKey', null);
        try {
            $this->object->verify();
        } catch(\Exception $e) {
            $this->assertInstanceOf('Airbrake\Exception', $e);
        }
    }
}
