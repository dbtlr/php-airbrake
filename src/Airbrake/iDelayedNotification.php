<?php
namespace Airbrake;

/**
 * Airbrake iDelayedNotification interface.
 * 
 * An interface to create notifications to be sent later to the Airbrake API
 *
 * @package        Airbrake
 */
interface iDelayedNotification
{
    // $staticNotifyMethod will be Connection::notify
    // $xml the XML content to be sent
    // $errorNotificationCallback is a callback to handle errors (a function that takes an AirbrakeException as argument)
    // must return true iff the task was succesfully created
    public static function createDelayedNotification($staticNotifyMethod, $xml, $apiEndPoint, $timeout, $headers, $errorNotificationCallback = null);
}
