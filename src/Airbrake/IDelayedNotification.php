<?php
namespace Airbrake;

/**
 * Airbrake IDelayedNotification interface.
 * 
 * An interface to create notifications to be sent later to the Airbrake API
 *
 * @package        Airbrake
 */
interface IDelayedNotification
{
    // must return true iff the task was succesfully created
    public static function createDelayedNotification(
        $eventId, $json, $apiEndPoint, $timeout, $headers, $errorMessage,
        $dbReportClass = null, $errorNotificationCallback = null, $secondaryCallback = null
    );
}
