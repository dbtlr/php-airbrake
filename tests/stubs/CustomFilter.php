<?php

class CustomFilter implements Airbrake\Filter\FilterInterface
{
  public function __construct($key_to_filter)
  {
    $this->key_to_filter = $key_to_filter;
  }
  public function filter(&$array)
  {
    unset($array[$this->key_to_filter]);
  }
}
