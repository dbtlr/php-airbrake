<?php

include_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs/CustomFilter.php';

class ConfigurationTest extends PHPUnit_Framework_TestCase
{
  public function testFiltersApplied()
  {
    $initial = ['foo' => 1, 'bar' => 2];
    $expected = ['foo' => 1];

    $config = new Airbrake\Configuration('test', ['postData' => $initial]);
    $config->addFilter('bar');
    $this->assertEquals($expected, $config->getParameters());
  }

  public function testFiltersNotAppliedOnUnfilteredParameters()
  {
    $initial = ['foo' => 1, 'bar' => 2];
    $expected = ['foo' => 1, 'bar' => 2];

    $config = new Airbrake\Configuration('test', ['postData' => $initial]);
    $config->addFilter('bar');
    $this->assertEquals($expected, $config->getUnfilteredParameters());
  }

  public function testCustomFilter()
  {
    $initial = ['foo' => 1, 'bar' => 2];
    $expected = ['bar' => 2];
    $instance = new CustomFilter('foo');

    $config = new Airbrake\Configuration('test', ['postData' => $initial]);
    $config->addFilter($instance);
    $this->assertEquals($expected, $config->getParameters());
  }
}
