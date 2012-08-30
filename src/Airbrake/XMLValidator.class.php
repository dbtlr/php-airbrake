<?php
namespace Airbrake;

/**
 * Airbrake XML validation class, to be able to check that our XML are up to Aribrake's specifications
 *
 * @package     Airbrake
 * @license     http://www.opensource.org/licenses/mit-license.php
 */
class XMLValidator
{
    // found at http://airbrake.io/airbrake_<Airbrake_notification_API_version>.xsd (currrent version is 2_2)
    const XSD_SCHEMA_FILE = 'lib/vendor/airbrake/src/Airbrake/airbrake_2_2.xsd';

    /**
     * @param string $xml - an XML string to validate
     * @return bool - true if valid, false otherwise
     **/
    public static function validateXML($xml)
    {
        $domXML = new \DOMDocument();
        $domXML->loadXML($xml);

        // disable ugly error output (cf http://php.net/manual/en/ref.libxml.php)
        libxml_use_internal_errors(true);
        // empty error cache
        libxml_clear_errors();

        return $domXML->schemaValidate(self::XSD_SCHEMA_FILE);
    }

    public static function prettyPrintXMLValidationErrors()
    {
        $result = '';
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            $result .= self::prettyPrintXMLValidationError($error);
        }
        return $result;
    }

    private static function prettyPrintXMLValidationError(\LibXMLError $error)
    {
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $result = "Warning $error->code: ";
                break;
            case LIBXML_ERR_ERROR:
                $result = "Error $error->code: ";
                break;
            case LIBXML_ERR_FATAL:
                $result = "Fatal Error $error->code: ";
                break;
        }
        $result .= trim($error->message);
        if ($error->file) {
            $result .= " in $error->file";
        }
        $result .= " on line $error->line\n";

        return $result;
    }

}
