<?php
namespace Airbrake;

/**
 * Airbrake connection class.
 *
 * @package		Airbrake
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
	 * @return string
	 **/
	public function send(Notice $notice)
  {
    //If the users has opted for asyncronous
    //notifications, invoke $this->sendAsync 
    if($this->configuration->async) {
      return $this->sendAsync($notice);
    } else {
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
	  	curl_close($curl);

		  return $return;
    }
  }

  /*
   * Opens a socket to Airbrake, sends the notice, and 
   * closes the socket right away. No need to wait for
   * a response if the user has opted for async notifications
   */
  private function sendAsync(Notice $notice) {
    $fp = fsockopen($this->configuration->host,80);
    if(!$fp) {
      return false;
    }

    $xml = $notice->toXml($this->configuration);

    $http = "POST {$this->configuration->resource} HTTP/1.1\r\n";
    $http.= "Host: {$this->configuration->host}\r\n";
    $http.= "User-Agent: Airbrake PHP Notifier\r\n";
    $http.= "Content-Type: application/x-www-form-urlencoded\r\n";
    $http.= "Connection: close\r\n";
    $http.= "Content-Length: ".strlen($xml)."\r\n\r\n";
    $http.= $xml;

    fwrite($fp, $http);
    fclose($fp);

    return true;
  }
}
