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
    // Should write the Airbrake report (as an array) to the DB
    // Must return the object on success, null on failure
    public static function logInDB($reportArray);

    // Must return a non-false ID that can be used to retrieve the object from the DB
    // Will be called after logInDB, and will be fed back to updateLinkById to retrieve the same one
    public function getId();

    // Will be included in the report sent to Airbrake (allows for some fancy processing of the raw ID)
    public function getStringId();

    // Called by Connection::notify to update the DB record and save the link to the Airbrake report
    // Must return true iff the save was successful
    public static function updateLinkById($id, $link);
}
