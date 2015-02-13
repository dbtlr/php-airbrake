<?php

namespace Airbrake;

class ErrorReportingFilterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider errorReportingProvider
     */
    public function testErrorReportingLevels($expected, $error_level, $config_level)
    {
        $config = array('errorReportingLevel' => $config_level);
        $config = new Configuration('1', $config);
        $instance = new EventFilter\Error\ErrorReporting($config);

        $result = $instance->shouldSendError($error_level, 'test');
        $this->assertEquals($expected, $result);
    }

    public function errorReportingProvider()
    {
        return array(
            array(true, E_ERROR, E_ERROR),
            array(true, E_WARNING, E_ERROR | E_WARNING),
            array(true, E_WARNING, E_WARNING | E_STRICT),
            array(false, E_NOTICE, E_WARNING | E_STRICT | E_ERROR),
            array(false, E_STRICT, E_WARNING | E_NOTICE | E_ERROR)
        );
    }
}
