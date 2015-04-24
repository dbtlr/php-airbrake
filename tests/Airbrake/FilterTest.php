<?php

namespace Airbrake;

class FilterTest extends \PHPUnit_Framework_TestCase
{
    public function testNoFilter()
    {
        $initial = array('foo' => 1, 'bar' => 2);
        $filters = array();
        $expected = array('foo' => 1, 'bar' => 2);
        $this->doTest($initial, $filters, $expected);
    }

    public function testSingleFilter()
    {
        $initial = array('foo' => 1, 'bar' => 2);
        $filters = array('foo');
        $expected = array('bar' => 2);
        $this->doTest($initial, $filters, $expected);
    }

    public function testSingleFilterArray()
    {
        $initial = array('foo' => array('test' => 1), 'bar' => 2);
        $filters = array('foo');
        $expected = array('bar' => 2);
        $this->doTest($initial, $filters, $expected);
    }

    public function testMultipleFilters()
    {
        $initial = array('foo' => 1, 'bar' => 2);
        $filters = array('foo', 'bar');
        $expected = array();
        $this->doTest($initial, $filters, $expected);
    }

    public function testSingleChildFilter()
    {
        $initial = array('foo' => array('test' => 1), 'bar' => 2);
        $filters = array('foo[test]');
        $expected = array('foo' => array(), 'bar' => 2);
        $this->doTest($initial, $filters, $expected);
    }

    public function testMultipleChildFilters()
    {
        $initial = array('foo' => array('test' => array('bar' => 1)), 'bar' => 2);
        $filters = array('foo[test][bar]');
        $expected = array('foo' => array('test' => array()), 'bar' => 2);
        $this->doTest($initial, $filters, $expected);
    }

    public function testLevelConflict()
    {
        $initial = array('foo' => array('bar' => 1), 'bar' => 2);
        $filters = array('bar');
        $expected = array('foo' => array('bar' => 1));
        $this->doTest($initial, $filters, $expected);
    }

    private function doTest($initial, $filters, $expected)
    {
        $test = $initial;
        foreach ($filters as $filter) {
            $filter = new Filter($filter);
            $filter->filter($test);
        }
        $this->assertEquals($expected, $test);
    }
}
