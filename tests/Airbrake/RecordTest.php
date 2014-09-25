<?php

namespace Airbrake;

class RecordTest extends \PHPUnit_Framework_TestCase
{
    public function testCanGetDataThatWasSetIntoRecord()
    {
        $obj = new RecordTestMock();
        $obj->set('key1', 1);
        $obj->set('key2', 2);

        $this->assertEquals(1, $obj->get('key1'));
        $this->assertEquals(2, $obj->get('key2'));
    }

    public function testCanLoadDataFromArray()
    {
        $data = array('key1' => 1, 'key2' => 2, 'key3' => 3, 'key4' => 4);
        $obj  = new RecordTestMock();
        $obj->load($data);

        $this->assertEquals(1, $obj->get('key1'));
        $this->assertEquals(2, $obj->get('key2'));
        $this->assertEquals(3, $obj->get('key3'));
        $this->assertEquals(4, $obj->get('key4'));
    }

    public function testCanDumpLoadedData()
    {
        $data = array('key1' => 1, 'key2' => 2, 'key3' => 3, 'key4' => 4);
        $obj  = new RecordTestMock();
        $obj->load($data);

        $this->assertEquals($data, $obj->toArray());
    }

    public function testCanGetKeysForRecord()
    {
        $obj = new RecordTestMock();
        $this->assertEquals(array('key1', 'key2', 'key3', 'key4'), $obj->getKeys());
    }

    public function testWillReturnNullOnUnknownKeys()
    {
        $data = array('key1' => 1, 'key2' => 2, 'key3' => 3, 'key4' => 4);
        $obj  = new RecordTestMock();
        $obj->load($data);

        $this->assertNull($obj->get('Unkown'));
    }

    public function testCanAccessObjectLikeArray()
    {
        $data = array('key1' => 1, 'key2' => 2, 'key3' => 3, 'key4' => 4);
        $obj  = new RecordTestMock();
        $obj->load($data);

        $this->assertEquals(1, $obj['key1']);

        $obj['key1'] = 10;
        $this->assertEquals(10, $obj['key1']);

        unset ($obj['key1']);
        $this->assertNull($obj['key1']);

        $this->assertTrue(isset ($obj['key1']));
    }

    public function testCanLoopThroughData()
    {
        $data = array('key1' => 1, 'key2' => 2, 'key3' => 3, 'key4' => 4);
        $obj  = new RecordTestMock();
        $obj->load($data);

        $newArray = array();

        foreach ($obj as $key => $value) {
            $newArray[$key] = $value;
        }

        $this->assertEquals($data, $newArray);
    }
}

class RecordTestMock extends Record
{
    protected $dataStore = array(
        'key1' => null,
        'key2' => null,
        'key3' => null,
        'key4' => null,
    );
}
