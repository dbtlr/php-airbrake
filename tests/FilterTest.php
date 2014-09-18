<?php

include_once __DIR__ . '/../vendor/autoload.php';

class FilterTest extends PHPUnit_Framework_TestCase
{
  //Purposefully not used a data provider here - the ugly catscratchings of [] 
  //to denote the initial, filters and expected made it very difficult to read.

  public function testNoFilter()
  {
    $initial = ['foo' => 1, 'bar' => 2];
    $filters = [];
    $expected = ['foo' => 1, 'bar' => 2];
    $this->doTest($initial, $filters, $expected);
  }

  public function testSingleFilter()
  {
    $initial = ['foo' => 1, 'bar' => 2];
    $filters = ['foo'];
    $expected = ['bar' => 2];
    $this->doTest($initial, $filters, $expected);
  }

  public function testSingleFilterArray()
  {
    $initial = ['foo' => ['test' => 1], 'bar' => 2];
    $filters = ['foo'];
    $expected = ['bar' => 2];
    $this->doTest($initial, $filters, $expected);
  }

  public function testMultipleFilters()
  {
    $initial = ['foo' => 1, 'bar' => 2];
    $filters = ['foo', 'bar'];
    $expected = [];
    $this->doTest($initial, $filters, $expected);
  }

  public function testSingleChildFilter()
  {
    $initial = ['foo' => ['test' => 1], 'bar' => 2];
    $filters = ['foo[test]'];
    $expected = ['foo' => [], 'bar' => 2];
    $this->doTest($initial, $filters, $expected);
  }

  public function testMultipleChildFilters()
  {
    $initial = ['foo' => ['test' => ['bar' => 1]], 'bar' => 2];
    $filters = ['foo[test][bar]'];
    $expected = ['foo' => ['test' => []], 'bar' => 2];
    $this->doTest($initial, $filters, $expected);
  }

  public function testLevelConflict()
  {
    $initial = ['foo' => ['bar' => 1], 'bar' => 2];
    $filters = ['bar'];
    $expected = ['foo' => ['bar' => 1]];
    $this->doTest($initial, $filters, $expected);
  }

  private function doTest($initial, $filters, $expected)
  {
    $test = $initial;
    foreach($filters as $filter){
      $filter = new Airbrake\Filter($filter);
      $filter->filter($test);
    }
    $this->assertEquals($expected, $test);
  }
}
