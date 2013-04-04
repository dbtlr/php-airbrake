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
        $this->addHeader(self::getDefaultHeaders($configuration));
    }

    /**
     * Add a header to the connection.
     *
     * @param string header
     */
    private function addHeader($header)
    {
        $this->headers += (array)$header;
    }

    public static function getDefaultHeaders(Configuration $configuration)
    {
        $userAgent = Version::NAME.'/'.Version::NUMBER;
        return array('User-Agent: '.$userAgent,
            'X-Sentry-Auth: Sentry sentry_timestamp='.time().', sentry_client='.$userAgent.', sentry_version='.Version::API.', sentry_key='.$configuration->apiKey,
            "Content-Type: application/octet-stream"
        );
    }

    /**
     * @param Airbrake\Notice $notice
     * @return string
     **/
    public function send(Notice $notice)
    {
        $config = $this->configuration;
        $xml    = $notice->buildJSON($config);

        $result = self::notify($xml, $config->apiEndPoint, $config->timeout, $this->headers, $notice->errorMessage,
            $config->arrayReportDatabaseClass, $notice->dbId,
            function(AirbrakeException $e) use($config) { $config->notifyUpperLayer($e, true); },
            function(AirbrakeException $e) use($config) { $config->notifyUpperLayer($e, true, true); }
        );

        return $result;
    }

    public static function notify($data, $apiEndPoint, $timeout, $headers, $errorMessage, $dbReportClass = null, $dbId = null, $errorNotificationCallback = null, $secondaryCallback = null)
    {
        $curl = curl_init();

        // compress and encode
        $compressedData = base64_encode(gzcompress($data));

        curl_setopt($curl, CURLOPT_URL, $apiEndPoint);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($curl, CURLOPT_POSTFIELDS,$compressedData);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $answer = curl_exec($curl);

        $responseStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($responseStatus != 200) {
            $callback = $errorNotificationCallback && is_callable($errorNotificationCallback) ? $errorNotificationCallback :
                ($secondaryCallback && is_callable($secondaryCallback) ? $secondaryCallback : null);
            if ($callback) {
                $exception = new AirbrakeException("HTTP response status: $responseStatus\n\nResponse: $answer\n\nOriginal data sent: $data");
                $exception->setShortDescription('Aibrake critical error when posting a report');
                call_user_func_array($errorNotificationCallback, array($exception));
            }
        }

        curl_close($curl);

        return $answer;
    }
}
