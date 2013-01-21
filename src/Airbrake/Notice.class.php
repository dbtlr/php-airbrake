<?php
namespace Airbrake;

use SimpleXMLElement;

require_once 'XMLValidator.class.php';
require_once 'AirbrakeRootXMLElement.class.php';

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
        $doc = new AirbrakeRootXMLElement('<notice />');
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
                $method = $this->getMethodString($entry);
                if ($method !== null) {
                    $line->addAttribute('method', $method);
                }
            }
        }

        $request = $doc->addChild('request');
        $request->addChild('url', $configuration->get('url'));
        $request->addChild('component', $configuration->get('component'));
        $request->addChild('action', $configuration->get('action'));

        // report usual data + whatever additional vars have been defined
        // + the full error message in case Airbrake truncates it + the time (for delayed notifications)
        $cgi_data = array_merge($configuration->get('serverData'),
                                $configuration->getAdditionalCgiParams(),
                                array('fullErrorMessage' => $this->errorMessage,
                                      'time'             => date('c'),
                                      'timestamp'        => time())
                                );
        $this->array2Node($request, 'params', $this->sanitize($configuration->getParameters()));
        $this->array2Node($request, 'session', $this->sanitize($configuration->get('sessionData')));
        $this->array2Node($request, 'cgi-data', $this->sanitize($cgi_data));

        $this->callProcessAsArrayCallback($doc, $configuration);

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
        $s = preg_replace('/\v/', ' ', $s);
        return htmlspecialchars($s);
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

    // generates the "method" string to be included in AB's record, from a backtrace entry
    private function getMethodString(array $entry)
    {
        if (!isset($entry['function'])) {
            return null;
        }

        $result = $entry['function'];

        // prepend the class name and function type if available
        if (isset($entry['class']) && isset($entry['type'])) {
            $result = $entry['class'].$entry['type'].$result;
        }

        // append arguments
        if (isset($entry['args'])) {
            $args = array_map(array($this, 'argToString'), $entry['args']);
        } else {
            $args = array();
        }
        $result .= '('.implode(', ', $args).')';

        return $result;
    }
    // returns a string to represent any argument
    // more specifically, objects are just represented by their class' name
    // resources by their type
    // and all others are var_export'ed
    const MAX_LEVEL = 10; // the maximum level up to which arrays will be exported (inclusive)
    private function argToString($arg, $level = 1)
    {
        if (is_array($arg) || $arg instanceof Traversable) {
            $result = $this->singleArgToString($arg)." (\n";
            if ($level > self::MAX_LEVEL) {
                $result .= "... TOO MANY LEVELS IN THE ARRAY, NOT DISPLAYED ...\n";
            } else {
                $prefix = $this->buildArrayPrefix($level);
                foreach ($arg as $key => $value) {
                    $result .= $prefix.var_export($key, true).' => '.$this->argToString($value, $level + 1).",\n";
                }
            }
            $result .= ')';
            return $result;
        } else {
            return $this->singleArgToString($arg);
        }
    }

    private function singleArgToString($arg)
    {
        if (is_object($arg)) {
            return 'Object '.get_class($arg);
        } elseif (is_resource($arg)) {
            return 'Resource '.get_resource_type($arg);
        } elseif (is_array($arg)) {
            return 'array';
        } else {
            // should be a scalar then
            return gettype($arg).' '.var_export($arg, true);
        }
    }

    // # of spaces in the base array prefix
    const BASE_ARRAY_PREFIX_LENGTH = 2;
    private function buildArrayPrefix($level)
    {
        return str_repeat(' ', $level * self::BASE_ARRAY_PREFIX_LENGTH);
    }

    private function callProcessAsArrayCallback(AirbrakeRootXMLElement $doc, Configuration $configuration)
    {
        try {
            $callback = $configuration->get('processReportAsArrayCallback');
            if ($callback) {
                if (is_callable($callback)) {
                    call_user_func($callback, $doc->asArray());
                } else {
                    throw new \Exception('Invalid processReportAsArrayCallback provided: '.var_export($callback, true));
                }
            }
        } catch (\Exception $ex) {
            $configuration->notifyUpperLayer($ex);
        }
    }
}
