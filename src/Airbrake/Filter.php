<?php

namespace Airbrake;

class Filter implements Filter\FilterInterface
{
    private $key_parts = array();

    public function __construct($key_name)
    {
        $this->splitKeyName($key_name);
    }

    public function filter(&$array)
    {
        $current = &$array;
        $last_element = end(array_keys($this->key_parts));

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
        foreach($this->key_parts as $index => $key_part){
            if (!isset($current[$key_part])){
                break;
            }
            if ($index == $last_element){
                unset($current[$key_part]);
                break;
            }
            $current = &$current[$key_part];
        }
    }

    private function splitKeyName($key_name)
    {
        /**
         * This breaks a form name formatted post name into it's constituent 
         * parts. e.g. form[subform][id] becomes an array containing:
         * ['form', 'subform', 'id']
         */
        $r = '/\[([^]]+)\]/';
        $parts = preg_split($r, $key_name, -1, PREG_SPLIT_DELIM_CAPTURE);
        $parts = array_filter($parts, 'strlen');
        $this->key_parts = $parts;
    }
}
