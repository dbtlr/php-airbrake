PHP Airbrake
============

A PHP module to make use of the [Airbrake API](http://help.airbrake.io/kb/api-2/api-overview) for storing error messages. This is based loosely on the [official Ruby implementation](https://github.com/airbrake/airbrake) from the Airbrake team.

Exception Handler Example
=========================

The preferred method for this to be used is via error and exception handlers, so that you do not have to manually call the configuration and client class every time. This is simply done by calling up the built in error handler and passing in your API key to its start() method like so:

```php
require 'src/Airbrake/EventHandler.php';
Airbrake\EventHandler::start('[your api key]');
```

Optionally, you may pass a second parameter as TRUE to the start() method, in order to enable the logging of warning level messages as well. This is disabled by default, as it may considered too noisy, depending on the quality of the code base. There is also a third options array that may be passed, which will load many of the more common configuration options. These options are located below.

Basic Usage Example
===================

If calling the class directly and not through an exception handler, it would be done like this:

```php
require_once 'src/Airbrake/Client.php';
require_once 'src/Airbrake/Configuration.php';

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