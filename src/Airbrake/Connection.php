<?php
namespace Airbrake;

/**
 * Airbrake connection class.
 *
 * @package		Airbrake
 * @author		Drew Butler <drew@abstracting.me>
 * @copyright	(c) 2011 Drew Butler
 * @license		http://www.opensource.org/licenses/mit-license.php
 */
class Connection
{

    protected $configuration = null;
    protected $headers = array();

    const XSD_SCHEMA_FILE = 'lib/vendor/airbrake/src/Airbrake/airbrake_2_2.xsd'; // found at http://airbrake.io/airbrake_2_2.xsd

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

        // if we asked to validate the XML, then do so
        if ($validateXml || \sfConfig::get('sf_airbrake_validate_xml')) {
            $domXML = new \DOMDocument();
            $domXML->loadXML($xml);

            // disable ugly error output (cf http://php.net/manual/en/ref.libxml.php)
            libxml_use_internal_errors(true);

            if (!$domXML->schemaValidate(self::XSD_SCHEMA_FILE)) {
                $this->sendEmail('Airbrake warning : XML validating failed',
                    "Validation errors:\n".$this->prettyPrintXMLValidationErrors().
                    "\nwhen validating the following XML:\n$xml\nagainst XSD schema file ".self::XSD_SCHEMA_FILE);
            }
        }

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
            $this->sendEmail('Aibrake critical error when posting a report',
                "HTTP response status: $response_status\n\nResponse: $return\n\nOriginal XML sent: $xml");
        }

		curl_close($curl);

		return $return;
	}

    private function sendEmail($subject, $body)
    {
        \sfContext::getInstance()->getMailer()->composeAndSend(
            array(\sfConfig::get('sf_logger_email') => \sfConfig::get('sf_app').' - airbrake'),
            \sfConfig::get('sf_logger_email'),
            $subject,
            $body
        );
    }

    private function prettyPrintXMLValidationErrors()
    {
        $result = '';
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            $result .= $this->prettyPrintXMLValidationError($error);
        }
        libxml_clear_errors();
        return $result;
    }

    private function prettyPrintXMLValidationError(\LibXMLError $error)
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
