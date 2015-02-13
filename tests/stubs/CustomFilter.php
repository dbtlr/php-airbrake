<?php

namespace Airbrake\Stub;

use Airbrake\Filter\FilterInterface;

class CustomFilter implements FilterInterface
{
    /** @var string  */
    protected $keyToFilter;

    /**
     * @param string $key
     */
    public function __construct($key)
    {
        $this->keyToFilter = $key;
    }

    /**
     * @param array $array
     */
    public function filter(&$array)
    {
        unset($array[$this->keyToFilter]);
    }
}
