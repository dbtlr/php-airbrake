<?php
namespace Airbrake;

use Airbrake\AirbrakeException as AirbrakeException;

/**
 * Airbrake connection class.
 *
 * @package        Airbrake
 * @author         Drew Butler <drew@abstracting.me>
 * @copyright      (c) 2011 Drew Butler
 * @license        http://www.opensource.org/licenses/mit-license.php
 */
class Connection
{

    protected $configuration = null;
    protected $headers = array();

    /**
     * Build the object with the airbrake Configuration.
     *
     * @param Airbrake\Configuration $configuration
     */
    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;

        $this->addHeader(self::getDefaultHeaders());
    }

    /**
     * Add a header to the connection.
     *
     * @param string header
     */
    public function addHeader($header)
    {
        $this->headers += (array)$header;
    }

    public static function getDefaultHeaders()
    {
        return array(
            'Accept: text/xml, application/xml',
            'Content-Type: text/xml'
        );
    }

    /**
     * @param Airbrake\Notice $notice
     * @return string
     **/
    public function send(Notice $notice)
    {
        $config = $this->configuration;
        $xml    = $notice->toXml($config);

        $result = self::notify($xml, $config->apiEndPoint, $config->timeout, $this->headers, $notice->errorMessage,
            $config->arrayReportDatabaseClass, $notice->dbId,
            function(AirbrakeException $e) use($config) { $config->notifyUpperLayer($e, true); },
            function(AirbrakeException $e) use($config) { $config->notifyUpperLayer($e, true, true); }
        );

        // if we asked to validate the XML, then do so
        if ($this->configuration && $this->configuration->get('validateXML')
            && !XMLValidator::validateXML($xml)) {

            $exception = new AirbrakeException("Validation errors:\n".XMLValidator::prettyPrintXMLValidationErrors().
                "\nwhen validating the following XML:\n$xml\nagainst XSD schema file ".XMLValidator::XSD_SCHEMA_FILE);
            $exception->setShortDescription('Airbrake warning : XML validating failed');
            throw $exception;
        }

        return $result;
    }

    public static function notify($xml, $apiEndPoint, $timeout, $headers, $errorMessage, $dbReportClass = null, $dbId = null, $errorNotificationCallback = null, $secondaryCallback = null)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $apiEndPoint);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $answer = curl_exec($curl);

        $responseStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($responseStatus != 200) {
            if (self::isThrottlingErrorMessage($responseStatus, $answer)
                && $secondaryCallback
                && is_callable($secondaryCallback))
            {
                // just log 'over the limit' errors to the secondary notifier, if any exists, otherwise go with the primary one
                $exception = new AirbrakeException("Over plan limit, didn't log error: $errorMessage");
                $exception->setShortDescription('Airbrake API throttling error');
                $exception->setLogNamespace('airbrake_api_throttling');
                call_user_func_array($secondaryCallback, array($exception));
            } elseif($errorNotificationCallback && is_callable($errorNotificationCallback)) {
                $exception = new AirbrakeException("HTTP response status: $responseStatus\n\nResponse: $answer\n\nOriginal XML sent: $xml");
                $exception->setShortDescription('Aibrake critical error when posting a report');
                call_user_func_array($errorNotificationCallback, array($exception));
            }
        }

        curl_close($curl);

        if ($dbReportClass && $dbId) {
            try {
                self::updateDbRecord($dbReportClass, $dbId, $answer);
            } catch (\Exception $ex) {
                if($errorNotificationCallback && is_callable($errorNotificationCallback)) {
                    $message = $ex->getMessage()."\nHTTP response status: $responseStatus\n\nResponse: $answer\n\nOriginal XML sent: $xml";
                    $exception = new AirbrakeException($message);
                    $exception->setShortDescription('Airbrake: error when updating local DB');
                    call_user_func_array($errorNotificationCallback, array($exception));
                }
            }
        }

        return $answer;
    }

    private static function updateDbRecord($dbReportClass, $dbId, $answer)
    {
        $xmlResponse = new \SimpleXMLElement($answer);
        if ($url = $xmlResponse->url) {
            if (!$dbReportClass::updateLinkById($dbId, $url)) {
                throw new \Exception('Error when updating report with DB ID'.$dbId);
            }
        } else {
            throw new \Exception('Malformed answer');
        }
    }

    // returns true iff the resulting error message says we posted over the plan limit
    private static function isThrottlingErrorMessage($responseStatus, $message)
    {
        return $responseStatus == 429 && preg_match('/Project \d+ is rate limited\. Please upgrade your account\./', $message)
            || $responseStatus == 503
                && (preg_match("/^You've performed too many requests \d+\/\d+$/", $message)
                || $message == 'You are in a cooldown period for making too many requests');
    }

}
