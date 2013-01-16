<?php
namespace Airbrake;

use SimpleXMLElement;

/**
 * @package     Airbrake
 * @license     http://www.opensource.org/licenses/mit-license.php
 */
class AirbrakeRootXMLElement extends SimpleXMLElement
{
    const VAR_STRING = 'var';
    const KEY_STRING = 'key';
    const XML_PATH_SEP = '.';

    const ERROR_BACKTRACE_PATH = '.error.backtrace';

    // a bunch of path that won't be exported  (of the form - e.g: .node OR .node.node.node etc)
    private static $unexportableNodes = array('.api-key', '.notifier');

    public function asArray()
    {
        return self::XMLElementToArray($this);
    }

    // pretty ugly to resort to static functions, but SimpleXMLElement's constructor is final...
    private static function XMLElementToArray(SimpleXMLElement $node, $pathSoFar = '')
    {
        $value = (string) $node;
        if ($value) {
            return $value;
        } else {
            $result = array();
            if ($pathSoFar === self::ERROR_BACKTRACE_PATH) {
                // pretty ugly, but the backtrace does need to be processed separately
                foreach ($node->children() as $child) {
                    $backtraceLine = array();
                    foreach ($child->attributes() as $attributeName => $attributeValue) {
                        $backtraceLine[$attributeName] = (string) $attributeValue;
                    }
                    $result[] = $backtraceLine;
                }
            } else {
                // standard case
                foreach ($node->children() as $child) {
                    $childName = self::getNodeName($child);
                    $newPath   = $pathSoFar.self::XML_PATH_SEP.$childName;
                    if (self::isXMLElementExportable($newPath)) {
                        $result[$childName] = self::XMLElementToArray($child, $newPath);
                    }
                }
            }
            return $result;
        }
    }

    private static function isXMLElementExportable($nodePath)
    {
        return !in_array($nodePath, self::$unexportableNodes);
    }

    private static function getNodeName(SimpleXMLElement $node)
    {
        $nodeName = $node->getName();
        if ($nodeName !== self::VAR_STRING) {
            return $nodeName;
        }

        /**
         * so the name is 'var'; and from Airbrake's doc :
         * "The params, session, and cgi-data elements can contain one or more var elements
         * for each parameter or variable that was set when the error occurred. Each var
         * element should have a @key attribute for the name of the variable, and element
         * text content for the value of the variable."
         */
        foreach ($node->attributes() as $name => $value) {
            if ($name === self::KEY_STRING) {
                return (string) $value;
            }
        }
        // we haven't found the 'key' attribute
        throw new \Exception('Malformed XML: \'var\' node with no \'key\' attribute');
    }

}
