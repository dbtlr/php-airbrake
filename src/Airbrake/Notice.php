<?php
namespace Airbrake;

use SimpleXMLElement;

/**
 * Airbrake notice class.
 *
 * @package    Airbrake
 * @author     Drew Butler <drew@dbtlr.com>
 * @copyright  (c) 2011-2013 Drew Butler
 * @license    http://www.opensource.org/licenses/mit-license.php
 */
class Notice extends Record
{
    /** @var array */
    protected $dataStore = array(
        'backtrace' => null,
        'errorClass' => null,
        'errorMessage' => null,
        'extraParameters' => null,
    );

    /**
     * Convert the notice to xml
     *
     * @param Configuration $configuration
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
        $env->addChild('hostname', $configuration->get('hostname'));
        $env->addChild('app_version', $configuration->get('appVersion'));

        $error = $doc->addChild('error');
        $error->addChild('class', $this->get('errorClass'));
        $error->addChild('message', htmlspecialchars($this->get('errorMessage')));

        $trace = $this->get('backtrace');

        if (count($trace) > 0) {
            $backtrace = $error->addChild('backtrace');
            foreach ($trace as $entry) {
                $method = isset($entry['class']) ? $entry['class'].'::' : '';
                $method .= isset($entry['function']) ? $entry['function'] : '';
                $line = $backtrace->addChild('line');
                $line->addAttribute('file', isset($entry['file']) ? $entry['file'] : '');
                $line->addAttribute('number', isset($entry['line']) ? $entry['line'] : '');
                $line->addAttribute('method', $method);
            }
        }

        $request = $doc->addChild('request');
        $request->addChild('url', $configuration->get('url'));
        $request->addChild('component', $configuration->get('component'));
        $request->addChild('action', $configuration->get('action'));

        $extras = $this->get('extraParameters');
        $configExtras = $configuration->get('extraParameters');

        if (!isset($extras)) {
            $extras = array();
        }
        if (!isset($configExtras)) {
            $configExtras = array();
        }

        $innerExtras = array_merge($extras, $configExtras);

        $params = array_merge($configuration->getParameters(), array('airbrake_extra' => $innerExtras));

        $this->array2Node($request, 'params', $params);
        $this->array2Node($request, 'session', $configuration->get('sessionData'));
        $this->array2Node($request, 'cgi-data', $configuration->get('serverData'));

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
}
