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

        $this->addHeader(array(
            'Accept: text/xml, application/xml',
            'Content-Type: text/xml'
        ));
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

    /**
     * @param Airbrake\Notice $notice
     * @param[optional] bool $validateXml = false - If true, validates the generated XML against the XSD schema defined
     *                                              in XSD_SCHEMA_FILE (if any error is found, it spams the tech team)
     * @return string
     **/
    public function send(Notice $notice, $validateXml = false)
    {
        $curl = curl_init();

        $xml = $notice->toXml($this->configuration);

        curl_setopt($curl, CURLOPT_URL, $this->configuration->apiEndPoint);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->configuration->timeout);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $return = curl_exec($curl);

        // check there was no error, and spam the tech team if there was one
        $response_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response_status != 200) {
            $exception = new AirbrakeException("HTTP response status: $response_status\n\nResponse: $return\n\nOriginal XML sent: $xml");
            $exception->setShortDescription('Aibrake critical error when posting a report');
            throw $exception;
        }

        curl_close($curl);

        // if we asked to validate the XML, then do so
        if ( ($validateXml ||
            ($this->configuration && $this->configuration->get('validateXML')) )
            && !XMLValidator::validateXML($xml)) {

            $exception = new AirbrakeException("Validation errors:\n".XMLValidator::prettyPrintXMLValidationErrors().
                "\nwhen validating the following XML:\n$xml\nagainst XSD schema file ".XMLValidator::XSD_SCHEMA_FILE);
            $exception->setShortDescription('Airbrake warning : XML validating failed');
            throw $exception;
        }

        return $return;
    }

}
