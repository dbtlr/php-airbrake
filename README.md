PHP Airbrake
============

A PHP module to make use of the [Airbrake API](http://help.airbrake.io/kb/api-2/api-overview) for storing error messages. This is based loosely on the [official Ruby implementation](https://github.com/airbrake/airbrake) from the Airbrake team.

Installation
============

The best way to install the library is by using [Composer](http://getcomposer.org). Add the following to `composer.json` in the root of your project:

``` javascript
{ 
  "require": {
    "dbtlr/php-airbrake": "dev-master"
  }
}
```

Then, on the command line:

``` bash
curl -s http://getcomposer.org/installer | php
php composer.phar install
```

Use the generated `vendor/autoload.php` file to autoload the library classes.

Exception Handler Example
=========================

The preferred method for this to be used is via error and exception handlers, so that you do not have to manually call the configuration and client class every time. This is simply done by calling up the built in error handler and passing in your API key to its start() method like so:

```php
<?php
require_once 'vendor/autoload.php';
Airbrake\EventHandler::start('[your api key]');
```

Optionally, you may pass a second parameter as TRUE to the start() method, in order to enable the logging of warning level messages as well. This is disabled by default, as it may considered too noisy, depending on the quality of the code base. There is also a third options array that may be passed, which will load many of the more common configuration options. These options are located below.

Basic Usage Example
===================

If calling the class directly and not through an exception handler, it would be done like this:

```php
<?php
require_once 'vendor/autoload.php';

$apiKey  = '[your api key]'; // This is required
$options = array(); // This is optional

$config = new Airbrake\Configuration($apiKey, $options);
$client = new Airbrake\Client($config);

// Send just an error message
$client->notifyOnError('My error message');

// Send an exception that may have been generated or caught.
try {
    throw new Exception('This is my exception');

} catch (Exception $exception) {
    $client->notifyOnException($exception);
}
```

The options array may be filled with data from the Configuration Options section, if you would like to override some of the default options. Otherwise, it can be ignored.

Using Resque
============

_This section assumes you are using the [PHP-Resque](https://github.com/chrisboulton/php-resque) project from [Chris Boulton](https://github.com/chrisboulton)._

In order to speed up polling time, it may be desirable to pair Airbrake with a Resque queue. In order to do this, you must simply include Resque in your project and pass in the queue option.

```php
<?php
require_once 'vendor/autoload.php';

Airbrake\EventHandler::start('[your api key]', true, array('queue' => 'airbrake'));
```

In order to start the requested queue, simply run this command.

```
QUEUE=airbrake APP_INCLUDE=vendor/autoload.php vendor/bin/resque
```

This will start the queue running properly.

Configuration Options
=====================

- **timeout** - Defaults to 2, this is how long the service will wait before giving up. This should be set to a sane limit, so as to avoid excessive page times in the event of a failure.
- **environmentName** - Defaults to 'production'. This can be changed to match the environment that you are working, which will help prevent messy logs, filled with non-production problems.
- **serverData** - This defaults to the $_SERVER array, but can be overridden with any array of data.
- **getData** - Defaults to the $_GET array
- **postData** - Defaults to the $_POST array
- **sessionData** - Defaults to the $_SESSION array
- **component** - This is the name of the component or controller that is running.
- **action** - The name of the action that was called.
- **projectRoot** - Defaults to the Document Root. May need to change based on the context of your application.
- **url** - The main URL that was requested.
- **hostname** - The hostname that was requested.
- **queue** - Optional - the name of the Resque queue to use.
- **secure** - Optional - Boolean that allows you to define if you want to hit the secure Airbrake endpoint.
- **errorReportingLevel** - Optional - functions the same way as the error_reporting php.ini setting (this is applied on top of show warnings parameter on the EventHandler::start method)

Filters
=======

You can add filters to the request data that will be sent to your Airbrake server by calling the addFilter or addFilters methods. The default is to define a filter via the form name attribute. For example, if you had a form like this:

```xhtml
<form method="post" action="/login">
  <label for="username">Username</label>
  <input id="username" name="user[email]" type="text" />
  
  <label for="password">Password</label>
  <input id="password" name="user[password]" type="password" />
</form>
```

You could filter out all of the user details with the code:

```php
<?php

$config = Aibrake\EventHandler::getClient()->getConfiguration();
$config->addFilter('user');
```

Or just the password by using the filter

```php
<?php

$config = Aibrake\EventHandler::getClient()->getConfiguration();
$config->addFilter('user[password]');
```

You can also define your own filter classes by implementing the
Airbrake\Filter\FilterInterface interface. 

```php
<?php

class MyFilter implements Airbrake\Filter\FilterInterface
{
  public function filter(&$post_data)
  {
    if (array_key_exists('some_key', $post_data)){
      unset($post_data['some_key']);
    }
  }
}
$config = Aibrake\EventHandler::getClient()->getConfiguration();
$config->addFilter(new MyFilter());
```

Error/Exception Filters
=============
You can also define your own filters for filtering PHP Errors. If, for example, you want to have strict warnings on, but have some legacy subsystem that generates a lot of strict warnings, you can do the following:

```php
<?php

class MyErrorFilter implements Airbrake\EventFilter\Error\FilterInterface
{
  public function shouldSendError($type, $message, $file, $line, $context = null)
  {
    if ($type == E_STRICT && preg_match('/LegacyController.php/', $file)){
      return false;
    }
  }
}

$airbrake = Airbrake\EventHandler::start();
$airbrake->addErrorFilter(new MyErrorFilter());

```

You can do the same thing for uncaught exceptions - say your project throws ACL exceptions that bubble up to Airbrake, you can filter them out like this:

```php
<?php

class MyExceptionFilter implements Airbrake\EventFilter\Exception\FilterInterface
{
  public function shouldSendException($exception)
  {
    return !($exception instanceof AclException);
  }
}

$airbrake = Airbrake\EventHandler::start();
$airbrake->addExceptionFilter(new MyExceptionFilter());
```
