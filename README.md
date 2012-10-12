PHP Airbrake
============

Official PHP module to make use of the [Airbrake API](http://help.airbrake.io/kb/api-2/api-overview) for storing error messages. This is based loosely on the [official Ruby implementation](https://github.com/airbrake/airbrake) from the Airbrake team.
Original implementation by [Drew Butler](https://github.com/nodrew).

Installation
============

The best way to install the library is by using [Composer](http://getcomposer.org). Add the following to `composer.json` in the root of your project:

``` javascript
{ 
    "require": {
        "airbrake/airbrake-php": "dev-master"
    }
}
```

Then, on the command line:

``` bash
curl -s http://getcomposer.org/installer | php
php composer.phar install
```

Use the generated `vendor/.composer/autoload.php` file to autoload the library classes.

Note that this repository was previously named php-airbrake, but has
since been renamed to airbrake-php (to keep in line with the Airbrake
client naming convention).
If your composer.json contains the old package name, please update it to
the one above.

Exception Handler Example
=========================

The preferred method for this to be used is via error and exception handlers, so that you do not have to manually call the configuration and client class every time. This is simply done by calling up the built in error handler and passing in your API key to its start() method like so:

```php
<?php
require_once 'airbrake-php/src/Airbrake/EventHandler.php';
Airbrake\EventHandler::start('[your api key]');
```

Optionally, you may pass a second parameter as TRUE to the start() method, in order to enable the logging of warning level messages as well. This is disabled by default, as it may considered too noisy, depending on the quality of the code base. There is also a third options array that may be passed, which will load many of the more common configuration options. These options are located below.

Ignoring Exceptions
===================

Sometimes you may want to ignore particular exceptions, and the
following is an example of how to do that.

```php
<?php
require_once "airbrake-php/src/Airbrake/EventHandler.php";

$apiKey = '[your api key]';
$options= array('filters' => array('IgnoredException'));

Airbrake\EventHandler::start($apiKey, false, $options);

class IgnoredException extends Exception {}

throw new IgnoredException()
?>
```

The Airbrake client will see that the filters array contains a string
matching the exception name, in this case, IgnoredException, and will
ignore notifying the API. 

Basic Usage Example
===================

If calling the class directly and not through an exception handler, it would be done like this:

```php
<?php
require_once 'airbrake-php/src/Airbrake/Client.php';
require_once 'airbrake-php/src/Airbrake/Configuration.php';

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

require_once 'airbrake-php/src/Airbrake/EventHandler.php';
require_once 'php-resque/lib/Resque.php';

Airbrake\EventHandler::start('[your api key]', true, array('queue' => 'airbrake'));
```

In order to start the requested queue, simply run this command.

```
QUEUE=airbrake APP_INCLUDE=airbrake-php/src/Airbrake/Client.php php php-resque/resque.php
```

This will start the queue running properly.

Asyncronous Notifications
=========================

By default, the notifier uses cURL to send the exceptions to Airbrake,
which will wait for a response back from the server. If this is not
derisable for your application, you can pass async => true into the 
options array and the notifier will send an asyncronous notification
using a socket connection.

```php
<?php
require_once "airbrake-php/src/Airbrake/EventHandler.php";

$apiKey = '[your api key]';
$options= array('async' => true);

Airbrake\EventHandler::start($apiKey, false, $options);

?>
```

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
- **filters** - Optional - A list of errors to ignore
- **async**   - Optional - Will asynchronously send exceptions if true
