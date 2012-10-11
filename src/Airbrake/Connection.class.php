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

        $result = self::notify($xml, $config->apiEndPoint, $config->timeout, $this->headers,
            function(AirbrakeException $e) use($config) { $config->notifyUpperLayer($e, true); });

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

    public static function notify($xml, $apiEndPoint, $timeout, $headers, $errorNotificationCallback = null)
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

        $response_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response_status != 200 && $errorNotificationCallback && is_callable($errorNotificationCallback)) {
            $exception = new AirbrakeException("HTTP response status: $response_status\n\nResponse: $answer\n\nOriginal XML sent: $xml");
            $exception->setShortDescription('Aibrake critical error when posting a report');
            call_user_func_array($errorNotificationCallback, array($exception));
        }

        curl_close($curl);

        return $answer;
    }

}
