<?php

namespace Airbrake;

use Airbrake\Stub\CustomFilter;

require_once __DIR__ . '/../stubs/CustomFilter.php';

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    public function testFiltersApplied()
    {
        $initial = array('foo' => 1, 'bar' => 2);
        $expected = array('foo' => 1);

        $config = new Configuration('test', array('postData' => $initial));
        $config->addFilter('bar');
        $this->assertEquals($expected, $config->getParameters());
    }

    public function testFiltersNotAppliedOnUnfilteredParameters()
    {
        $initial = array('foo' => 1, 'bar' => 2);
        $expected = array('foo' => 1, 'bar' => 2);

        $config = new Configuration('test', array('postData' => $initial));
        $config->addFilter('bar');
        $this->assertEquals($expected, $config->getUnfilteredParameters());
    }

    public function testCustomFilter()
    {
        $initial = array('foo' => 1, 'bar' => 2);
        $expected = array('bar' => 2);
        $instance = new CustomFilter('foo');

        $config = new Configuration('test', array('postData' => $initial));
        $config->addFilter($instance);
        $this->assertEquals($expected, $config->getParameters());
    }
}
