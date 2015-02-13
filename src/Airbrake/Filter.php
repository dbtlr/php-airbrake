<?php

namespace Airbrake;

/**
 * Airbrake standard filter class. Used to filter post/get data before it is
 * sent over to the server. Intended use case is to filter out sensitive
 * information such as passwords or credit card details.
 *
 * @package    Airbrake
 * @author     Leon Szkliniarz <leon@llamadigital.net>
 * @copyright  (c) 2014 Leon Szkliniarz
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
class Filter implements Filter\FilterInterface
{
    /** @var array */
    protected $keyParts = array();

    /**
     * Create a Filter class based on a string key_name. Expects key_name to be
     * in the same format as a form name - so to filter out the value inside
     * $_POST['user']['password'] you would give it the value 'user[password]'
     *
     * @param string $keyName
     */
    public function __construct($keyName)
    {
        $this->splitKeyName($keyName);
    }

    /**
     * Applies the current filters to the passed in array, unsetting any
     * elements which match the filter. Note that this works via reference and
     * will mutate its argument instead of returning a copy.
     *
     * @param string &$array
     */
    public function filter(&$array)
    {
        $current = &$array;

        $keys = array_keys($this->keyParts);
        $lastElement = end($keys);

        /**
         * This code is ugly and complicated because PHP has no way of unsetting
         * arbitrary depths inside arrays.
         *
         * The intended functionality is if you create a filter like:
         * 'form[subform][id]' then $_POST['form']['subform']['id'] will be
         * unset. This format was chosen because it's also how you would
         * represent that structure inside a form element name in your markup.
         *
         * It all works by keeping a reference to the current depth of the array,
         * iterating over key_parts (see splitKeyName), checking if the iterated
         * value exists as a key of the current reference and then setting the
         * current reference to be that key. If it's the last element of the
         * iteration then this value needs to be filtered and is removed.
         */
        foreach ($this->keyParts as $index => $keyPart) {
            if (!isset($current[$keyPart])) {
                break;
            }

            if ($index == $lastElement) {
                unset($current[$keyPart]);
                break;
            }

            $current = &$current[$keyPart];
        }
    }

    /**
     * @param string $keyName
     */
    protected function splitKeyName($keyName)
    {
        /**
         * This breaks a form name formatted post name into it's constituent
         * parts. e.g. form[subform][id] becomes an array containing:
         * ['form', 'subform', 'id']
         */
        $r = '/\[([^]]+)\]/';
        $parts = preg_split($r, $keyName, -1, PREG_SPLIT_DELIM_CAPTURE);
        $parts = array_filter($parts, 'strlen');
        $this->keyParts = $parts;
    }
}
