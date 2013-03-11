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
     * The ID of the record saved in the local DB (if applicable)
     */
    protected $_dbId = null;

    // the max length for a string listing all the arguments of a function
    const MAX_ALL_ARGS_STRING_LENGTH   = 5000;
    // the max length for a string representing an array argument of a function
    const MAX_ARRAY_ARG_STRING_LENGTH  = 1000;
    // the max length for a string representing a single argument of a function
    const MAX_SINGLE_ARG_STRING_LENGTH = 200;

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
        $error->addChild('message', self::sanitize($message));

        // do we need to compute backtrace arguments?
        $computeArgs = $configuration->sendArgumentsToAirbrake || (bool) $configuration->arrayReportDatabaseClass;

        if (count($this->backtrace) > 0) {
            $backtrace = $error->addChild('backtrace');
            foreach ($this->backtrace as $entry) {
                $line = $backtrace->addChild('line');
                $line->addAttribute('file', isset($entry['file']) ? $entry['file'] : '');
                $line->addAttribute('number', isset($entry['line']) ? $entry['line'] : '');
                $method = self::getMethodString($entry, $configuration, $computeArgs);
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
        $this->array2Node($request, 'params', self::sanitize($configuration->getParameters()));
        $this->array2Node($request, 'session', self::sanitize($configuration->get('sessionData')));
        $this->array2Node($request, 'cgi-data', self::sanitize($cgi_data));

        $this->saveInLocalDB($doc, $configuration);

        if ($computeArgs && !$configuration->sendArgumentsToAirbrake) {
            // we need to remove the arguments from the backtrace before sending them to Airbrake
            self::pruneArgs($doc);
        }

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
            self::addVarToNode($node, $key, $value);
        }
    }

    private static function addVarToNode(SimpleXMLElement &$node, $key, $value)
    {
        $node->addChild('var', self::sanitizeString($value))
             ->addAttribute('key', $key);
    }

    // cleans the inputs from chars not supported by SimpleXMLElements
    // (as of today, mainly vertical tabs \v)
    private static function sanitizeString($s)
    {
        $s = preg_replace('/\v/', ' ', $s);
        // we want to strip low ASCII as this is not supported by PHP's XML libs
        $s = filter_var($s, FILTER_SANITIZE_STRING, array('flags' => FILTER_FLAG_STRIP_LOW));
        return htmlspecialchars($s);
    }

    // recursively sanitizes arrays
    private static function sanitize($a)
    {
        if (is_string($a)) {
            return self::sanitizeString($a);
        } elseif(is_array($a)) {
            foreach ($a as $key => $value) {
                $a[$key] = self::sanitize($value);
            }
        }
        return $a;
    }

    // generates the "method" string to be included in AB's record, from a backtrace entry
    // set $computeArgs to false to not compute them
    private static function getMethodString(array $entry, Configuration $configuration, $computeArgs = true)
    {
        if (!isset($entry['function'])) {
            return null;
        }

        $result = $entry['function'];

        // prepend the class name and function type if available
        if (isset($entry['class']) && isset($entry['type'])) {
            $result = $entry['class'].$entry['type'].$result;
        }

        // append arguments, if necessary
        if ($computeArgs) {
            $args = array();
            if (isset($entry['args'])) {
                foreach ($entry['args'] as $arg) {
                    $args[] = self::argToString($arg, $configuration);
                }
            }
            $argsAsString = implode(', ', $args);
            if (strlen($argsAsString) > self::MAX_ALL_ARGS_STRING_LENGTH) {
                $argsAsString = substr($argsAsString, 0, self::MAX_ALL_ARGS_STRING_LENGTH);
                $argsAsString .= ' ... [ARGS LIST TRUNCATED]';
            }
            $result .= '('.$argsAsString.')';
        }

        return $result;
    }
    // returns a string to represent any argument
    // more specifically, objects are just represented by their class' name
    // resources by their type
    // and all others are var_export'ed
    const MAX_LEVEL = 10; // the maximum level up to which arrays will be exported (inclusive)
    private static function argToString($arg, Configuration $configuration, $level = 1)
    {
        if ($arg === null) {
            return 'NULL';
        }

        $maxLength = self::MAX_SINGLE_ARG_STRING_LENGTH;
        if (is_array($arg) || $arg instanceof Traversable) {
            $result = self::singleArgToString($arg, $configuration)." (\n";
            if ($level > self::MAX_LEVEL) {
                $result .= "... TOO MANY LEVELS IN THE ARRAY, NOT DISPLAYED ...\n";
            } else {
                $prefix = self::buildArrayPrefix($level);
                foreach ($arg as $key => $value) {
                    $result .= $prefix.var_export($key, true).' => '.self::argToString($value, $configuration, $level + 1).",\n";
                }
            }
            $result .= ')';
            $maxLength = self::MAX_ARRAY_ARG_STRING_LENGTH;
        } else {
            $result = self::singleArgToString($arg, $configuration);
        }

        if (strlen($result) > $maxLength) {
            $result = substr($result, 0, self::MAX_SINGLE_ARG_STRING_LENGTH);
            $result .= ' ... [ARG TRUNCATED]';
        }
        return $result;
    }

    private static function singleArgToString($arg, Configuration $configuration)
    {
        if (is_object($arg)) {
            return 'Object '.get_class($arg);
        } elseif (is_resource($arg)) {
            return 'Resource '.get_resource_type($arg);
        } elseif (is_array($arg)) {
            return 'array';
        // should be a scalar then, let's see if it's blacklisted
        } elseif($configuration->isScalarBlackListed($arg)) {
            return '[BLACKLISTED SCALAR]';
        } else {
            // a scalar, not blacklisted!
            return gettype($arg).' '.var_export($arg, true);
        }
    }

    // # of spaces in the base array prefix
    const BASE_ARRAY_PREFIX_LENGTH = 2;
    private static function buildArrayPrefix($level)
    {
        return str_repeat(' ', $level * self::BASE_ARRAY_PREFIX_LENGTH);
    }

    private function saveInLocalDB(AirbrakeRootXMLElement &$doc, Configuration $configuration)
    {
        try {
            if ($arrayReportDatabaseClass = $configuration->get('arrayReportDatabaseClass')) {
                if (!($dbObject = $arrayReportDatabaseClass::logInDB($doc->asArray()))) {
                    throw new \Exception('Error while logging Airbrake report into DB');
                }
                // we need to add the DB ID to the report
                if (!($dbId = $dbObject->getId()) || !($dbStringId = $dbObject->getStringId())) {
                    throw new \Exception('Couldn\'t retrieve ID for DB object '.var_export($dbObject, true));
                }
                $this->set('dbId', $dbId);
                $requestNode = self::findOrAddChild($doc, 'request');
                $cgiDataNode = self::findOrAddChild($requestNode, 'cgi-data');
                self::addVarToNode($cgiDataNode, 'dbId', $dbStringId);
            }
        } catch (\Exception $ex) {
            $configuration->notifyUpperLayer($ex);
        }
    }

    // tries to find $node's child with given $name, adds it if it doesn't exist
    private static function findOrAddChild(SimpleXMLElement &$node, $name)
    {
        if ($node->$name) {
            return $node->$name;
        }
        return $node->addChild($name);
    }

    // deletes arguments from the backtrace
    // useful when we want to save them to a local DB but not send them to Airbrake
    // admittedly not the prettiest piece of code out there, but does the job in a cheap way
    private static function pruneArgs(SimpleXMLElement &$doc)
    {
        $backtraceNode = $doc->error->backtrace;
        if (!$backtraceNode || !$backtraceNode->line) {
            return;
        }

        for ($i = 0; $i < $backtraceNode->line->count(); $i++) {
            $method = $backtraceNode->line[$i]['method'];
            if ($method && ($pos = strpos($method, '(')) !== false) {
                $doc->error->backtrace->line[$i]['method'] = substr($method, 0, $pos);
            }
        }
    }

}
