<?php
namespace Airbrake;

/**
 * Airbrake IDelayedNotification interface.
 * 
 * An interface to log the reports (as an array) in a local database
 *
 * @package        Airbrake
 */
interface IArrayReportDatabaseObject
{
    // Should write the Sentry report (as an array) to the DB
    // Must return the object's id on success, in hex format with max 32 chars
    // and null on failure
    public static function logInDB($reportArray, $timestamp);
}
