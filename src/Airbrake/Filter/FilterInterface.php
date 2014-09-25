<?php

namespace Airbrake\Filter;

/**
 * Interface for Airbrake post/get filters. This is here to allow you to define
 * your own filters should the standard implementation be insufficient
 * for your needs.
 *
 * @package    Airbrake
 * @author     Leon Szkliniarz <leon@llamadigital.net>
 * @copyright  (c) 2014 Leon Szkliniarz
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
interface FilterInterface
{
    /**
     * Apply a filter to the passed in array. Note this is intended to mutate
     * the original array and is passed in via reference.
     *
     * @param array &$array
     */
    public function filter(&$array);
}
