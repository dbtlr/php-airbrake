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
    const SERVICE_URL = 'http://airbrakeapp.com/notifier_api/v2/notices';

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
	 * @return string
	 **/
	public function send(Notice $notice)
	{
		$curl = curl_init();

        $xml = $notice->toXml($this->configuration);

		curl_setopt($curl, CURLOPT_URL, self::SERVICE_URL);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_HEADER, 0);
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->configuration->timeout);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        $return = curl_exec($curl);
		curl_close($curl);

		return $return;
	}    
}