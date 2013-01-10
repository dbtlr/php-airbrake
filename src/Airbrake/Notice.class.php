<?php
namespace Airbrake;

use SimpleXMLElement;

require_once 'XMLValidator.class.php';

/**
 * Airbrake notice class.
 *
 * @package     Airbrake
 * @author      Drew Butler <drew@abstracting.me>
 * @copyright   (c) 2011 Drew Butler
 * @license     http://www.opensource.org/licenses/mit-license.php
 */
class Notice extends Record
{
    /** 
     * The backtrace from the given exception or hash.
     */
    protected $_backtrace = null;

    /** 
     * The name of the class of error (such as RuntimeError)
     */
    protected $_errorClass = null;

    /** 
     * The message from the exception, or a general description of the error
     */
    protected $_errorMessage = null;

    /**
     * Convert the notice to xml
     * see doc @ http://help.airbrake.io/kb/api-2/notifier-api-version-22
     * 
     * @param Airbrake\Configuration $configuration
     * @return string
     */
    public function toXml(Configuration $configuration)
    {
        $doc = new SimpleXMLElement('<notice />');
        $doc->addAttribute('version', Version::API);
        $doc->addChild('api-key', $configuration->get('apiKey'));

        $notifier = $doc->addChild('notifier');
        $notifier->addChild('name', Version::NAME);
        $notifier->addChild('version', Version::NUMBER);
        $notifier->addChild('url', Version::APP_URL);

        $env = $doc->addChild('server-environment');
        $env->addChild('project-root', $configuration->get('projectRoot'));
        $env->addChild('environment-name', $configuration->get('environmentName'));

        $error = $doc->addChild('error');
        $error->addChild('class', $this->errorClass);
        $errorPrefix = null;
        if ($configuration->get('errorPrefix')) {
            $errorPrefix = $configuration->get('errorPrefix').' - ';
        }
        $message = ($errorPrefix ?: '').$this->errorMessage;
        $error->addChild('message', $this->sanitize($message));

        if (count($this->backtrace) > 0) {
            $backtrace = $error->addChild('backtrace');
            foreach ($this->backtrace as $entry) {
                $line = $backtrace->addChild('line');
                $line->addAttribute('file', isset($entry['file']) ? $entry['file'] : '');
                $line->addAttribute('number', isset($entry['line']) ? $entry['line'] : '');
                $line->addAttribute('method', isset($entry['function']) ? $entry['function'] : '');
            }
        }

        $request = $doc->addChild('request');
        $request->addChild('url', $configuration->get('url'));
        $request->addChild('component', $configuration->get('component'));
        $request->addChild('action', $configuration->get('action'));

        // report usual data + whatever additional vars have been defined
        // + the full error message in case Airbrake truncates it + the time (for delayed notifications)
        $cgi_data = array_merge($configuration->get('serverData'),
                                $configuration->getAdditionalParams(),
                                array('fullErrorMessage' => $this->errorMessage,
                                      'time'             => date('c'))
                                );
        $this->array2Node($request, 'params', $this->sanitize($configuration->getParameters()));
        $this->array2Node($request, 'session', $this->sanitize($configuration->get('sessionData')));
        $this->array2Node($request, 'cgi-data', $this->sanitize($cgi_data));

        return $doc->asXML();
    }

    /**
     * Add a Airbrake var block to an XML node.
     *
     * @param SimpleXMLElement $parentNode
     * @param string $key
     * @param array $params
     **/
    protected function array2Node($parentNode, $key, $params)
    {
        if (count($params) == 0) {
            return;
        }

        $node = $parentNode->addChild($key);
        foreach ($params as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode((array) $value);
            }
            
            // htmlspecialchars() is needed to prevent html characters from breaking the node.
            $node->addChild('var', htmlspecialchars($value))
                 ->addAttribute('key', $key);
        }
    }

    // cleans the inputs from chars not supported by SimpleXMLElements
    // (as of today, mainly vertical tabs \v)
    private function sanitizeString($s)
    {
        return preg_replace('/\v/', ' ', $s);
    }

    // recursively sanitizes arrays
    private function sanitize($a)
    {
        if (is_string($a)) {
            return $this->sanitizeString($a);
        } elseif(is_array($a)) {
            foreach ($a as $key => $value) {
                $a[$key] = $this->sanitize($value);
            }
        }
        return $a;
    }
}
