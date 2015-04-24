<?php

namespace Airbrake\Helper;

/**
 * PHP's trigger_error function only allows you to trigger errors with in
 * the E_USER_* levels - no one cares about these. Below are some methods
 * that use bad code to intentionally trigger non-fatal errors.
 **/
class TriggerErrors
{
  public function triggerWarning()
  {
    extract(null);
  }
  public function triggerNotice()
  {
    $foo = $bar;
  }
  public function triggerDeprecated()
  {
    if (function_exists('ereg')){
      //Deprecated in 5.3
      ereg('.', '.');
    }else{
      //Just in case PHP actually get rid of ereg at some point - this is
      //deprecated in 5.5 and probably isn't going anywhere anytime soon
      preg_replace('/(.+)/e', 'strlen($1)', 'strlen');
    }
  }
  public function triggerStrict()
  {
    $foo = array('bar' => 1);
    array_pop(array_keys($foo));
  }
  public function triggerRecoverableError()
  {
    $this->a();
  }
  public function triggerCompileWarning()
  {
    //This doesn't work too well with the onError handler
    include(__DIR__ . '/../includes/compile_warning.php');
  }
  public function triggerUserWarning()
  {
    trigger_error('test', E_USER_WARNING);
  }
  public function triggerUserNotice()
  {
    trigger_error('test', E_USER_NOTICE);
  }
  public function triggerCoreWarning()
  {
    //This doesn't work - the "on startup" bit of the error type makes me think
    //it will be impossible to implement this
    dl('asfasdasdfasdfasdfasdfasdff.so');
  }

  private function a(array $a)
  {
  }
}
