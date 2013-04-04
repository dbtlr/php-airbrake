<?php
namespace Airbrake;

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
     * The message from the exception, or a general description of the error
     */
    protected $_errorMessage = null;

    /**
     * The event level
     */
    protected $_level = null;

    // the max length for a string representing an array argument of a function
    const MAX_ARRAY_ARG_STRING_LENGTH  = 1000;
    // the max length for a string representing a single argument of a function
    const MAX_SINGLE_ARG_STRING_LENGTH = 200;

    /**
     * Convert the notice to xml
     * see doc @ http://help.airbrake.io/kb/api-2/notifier-api-version-22
     * 
     * @param Airbrake\Configuration $configuration
     * @return array
     */
    public function buildJSON(Configuration $configuration)
    {
        $timestamp = time();

        // basic options
        $result = array(
            'message'    => $this->errorMessage,
            'timestamp'  => date('c', $timestamp),
            'level'      => $this->level,
            'logger'     => Version::NAME,
            'platform'   => $configuration->platform,
            'servername' => gethostname()
        );
        // tags and extras
        foreach (array('tags', 'extras') as $key) {
            if (($callback = $configuration->get($key.'Callback')) && ($data = $callback->call())) {
                $result[$key] = $data;
            }
        }

        // now to the backtrace - do we need to compute backtrace arguments?
        $computeArgs = $configuration->sendArgumentsToAirbrake || (bool) $configuration->arrayReportDatabaseClass;
        if ($this->backtrace) {
            $frames = array();
            // we need to reverse the backtrace as Sentry expects the most recent event first
            for ($i = count($this->backtrace) - 1; $i >= 0; $i--) {
                if ($frame = self::getStacktraceFrame($this->backtrace[$i], $configuration, $computeArgs)) {
                    $frames[] = $frame;
                }
            }
            if ($frames) {
                $result['sentry.interfaces.Stacktrace']['frames'] = $frames;
            }
        }

        // and finally other interfaces!
        if (($interfacesCallback = $configuration->interfacesCallback) && ($interfaces = $interfacesCallback->call())) {
            foreach ($interfaces as $name => $data) {
                if ($data) {
                    $result['sentry.interfaces.'.$name] = $data;
                }
            }
        }

        // last but not least, the event id
        if ($arrayReportDatabaseClass = $configuration->arrayReportDatabaseClass) {
            // then we should be able to get the ID from the DB!
            try {
                if (!($eventId = $arrayReportDatabaseClass::logInDB($result, $timestamp))) {
                    throw new \Exception('Error while logging Airbrake report into DB');
                }
                $eventId = self::formatUuid($eventId);
            } catch (\Exception $ex) {
                $eventId = null;
                $configuration->notifyUpperLayer($ex);
            }
        }
        if (empty($eventId)) {
            $eventId = self::getRandomUuid4();
        }
        $result['event_id'] = $eventId;
        // we also add it to the actual report to be able to cross-reference to the DB
        $result['extra']['event_id'] = $eventId;

        if ($computeArgs && !$configuration->sendArgumentsToAirbrake) {
            // we need to remove the arguments from the backtrace before sending them to Airbrake
            self::pruneArgs($result);
        }

        return json_encode($result);
    }

    // generates a stacktrace for this entry (see http://sentry.readthedocs.org/en/latest/developer/interfaces/index.html)
    private static function getStacktraceFrame(array $entry, Configuration $configuration, $computeArgs = true)
    {
        if (!isset($entry['function']) && !isset($entry['file'])) {
            return null;
        }

        $result = array();
        // the function name
        if (isset($entry['function'])) {
            $function = $entry['function'];
            // prepend the class name and function type if available
            if (isset($entry['class']) && isset($entry['type'])) {
                $function = $entry['class'].$entry['type'].$function;
            }
            $result['function'] = $function;
        }

        // file and line number
        if (isset($entry['file'])) {
            $result['filename'] = $entry['file'];
        }
        if (isset($entry['line'])) {
            $result['lineno'] = $entry['line'];
        }

        // compute arguments, if necessary
        if ($computeArgs) {
            $args = array();
            if (isset($entry['args'])) {
                $i = 1;
                foreach ($entry['args'] as $arg) {
                    // numbering args in that way is kinda ugly, but necessary to comply with Sentry's expected format
                    $args[(string) $i++] = self::argToString($arg, $configuration);
                }
            }
            $result['vars'] = $args;
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
            $result = self::singleArgToString($arg, $configuration).' (';
            if ($level > self::MAX_LEVEL) {
                $result .= '... TOO MANY LEVELS IN THE ARRAY, NOT DISPLAYED ...';
            } else {
                $arrayString = '';
                foreach ($arg as $key => $value) {
                    $arrayString .= ($arrayString ? ', ' : '').var_export($key, true).' => '.self::argToString($value, $configuration, $level + 1);
                }
                $result .= $arrayString;
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

    // from http://sentry.readthedocs.org/en/latest/developer/client/ : the uuid is a 32-char long hex string
    const UUID_LENGTH = 32;
    // checks the uuid is not too long, is an hex string, and pads it if necessary with leading zeroes
    public static function formatUuid($uuid) {
        $uuid = (string) $uuid;
        if (strlen($uuid) > self::UUID_LENGTH) {
            throw new \Exception('UUID "'.$uuid.'" is too long! Can\'t be more than '.self::UUID_LENGTH.' characters long');
        }
        if (!preg_match('/^[a-fA-f0-9]*$/', $uuid)) {
            throw new \Exception('UUID must be an hexadecimal string! You gave '.$uuid);
        }
        $padding = str_repeat('0', self::UUID_LENGTH - strlen($uuid));
        return $padding.$uuid;
    }

    // deletes arguments from the backtrace
    // useful when we want to save them to a local DB but not send them to Airbrake
    private static function pruneArgs(array &$report)
    {
        if (array_key_exists('sentry.interfaces.Stacktrace', $report)) {
            foreach ($report['sentry.interfaces.Stacktrace']['frames'] as &$frame) {
                if (array_key_exists('vars', $frame)) {
                    unset($frame['vars']);
                }
            }
        }
    }

    /**
     * Generates a random uuid4 value
     * Only called if no local DB class is provided
     * Official spec, implementation taken from the official php-raven source code
     */
    private static function getRandomUuid4()
    {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,
            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
        return str_replace('-', '', $uuid);
    }
}
