<?php

namespace Airbrake\Filter;

interface FilterInterface
{
  public function filter(&$array);
}
