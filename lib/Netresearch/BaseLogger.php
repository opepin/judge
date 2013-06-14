<?php
namespace Netresearch;

use Symfony\Component\Console\Output\OutputInterface;
use Netresearch\IssueHandler;
use \Exception as Exception;
/**
 * Simple Logger contains main functions for output.
 *
 * @author Stefanie Drost<stefanie.drost@netresearch.de>
 */
class BaseLogger
{
    const TYPE_COMMENT = 'comment';

    const TYPE_NOTICE = 'info';

    const TYPE_ERROR = 'error';

    const VERBOSITY_NONE   = 0;

    const VERBOSITY_MIN    = 1;

    const VERBOSITY_MEDIUM = 5;

    const VERBOSITY_MAX    = 10;

    protected static $verbosity = self::VERBOSITY_MEDIUM;

    protected static $output;
    
    protected static $extVendor;
    protected static $extVersion;
    protected static $extName;
    protected static $extIdentifier;

    protected static $results = array();

    public static function setOutputInterface(OutputInterface $output)
    {
        self::$output = $output;
    }

    public static function setVerbosity($verbosity)
    {
        self::$verbosity = $verbosity;
    }

    public static function getVerbosity()
    {
        return self::$verbosity;
    }
    
    public static function setExtVendor($extVendor)
    {
        self::$extVendor = $extVendor;
    }
    
    public static function setExtVersion($extVersion)
    {
        self::$extVersion = $extVersion;
    }

    public static function setExtName($extName)
    {
        self::$extName = $extName;
    }

    public static function setExtIdentifier($extIdentifier)
    {
        self::$extIdentifier = $extIdentifier;
    }
    
    public static function getExtVendor()
    {
        return self::$extVendor;
    }

    public static function getExtVersion()
    {
        return self::$extVersion;
    }

    public static function getExtName()
    {
        return self::$extName;
    }

    public static function getExtIdentifier()
    {
        return self::$extIdentifier;
    }

    protected static function writeln($message, array $args = array(), $type = null)
    {
        if (self::VERBOSITY_NONE === self::$verbosity) {
            return;
        }
        if (self::VERBOSITY_MIN == self::$verbosity
            && self::TYPE_ERROR !== $type
        ) {
            return;
        }
        if (self::VERBOSITY_MEDIUM == self::$verbosity
            && self::TYPE_ERROR !== $type
            && self::TYPE_NOTICE !== $type
        ) {
            return;
        }

        if (!self::$output) {
            throw new Exception('No output interface given');
        }

        self::$output->writeln(
            is_null($type)
            ? vsprintf("$message", $args)
            : vsprintf("<$type>$message</$type>", $args)
        );
    }

    public static function log($message, array $args = array())
    {
        self::writeln($message, $args, self::TYPE_NOTICE);
    }

    public static function comment($message, array $args = array())
    {
        self::writeln($message, $args, self::TYPE_COMMENT);
    }

    public static function notice($message, array $args = array())
    {
        self::writeln($message, $args, self::TYPE_NOTICE);
    }

    public static function error($message, array $args = array(), $stopExecution = true)
    {
        self::writeln($message, $args, self::TYPE_ERROR);
        if ($stopExecution) {
            exit;
        }
    }

    public static function success($message, array $args = array())
    {
        self::notice($message, $args);
    }

    public static function warning($message, array $args = array())
    {
        self::comment($message, $args);
    }

    public static function addCheck($extension, $check)
    {
        if (!array_key_exists($extension, self::$results)) {
            self::$results[$extension] = array();
        }
        self::$results[$extension][$check] = array();
    }

    public static function setComments($extension, $check, $comments)
    {
        if (false == array_key_exists($extension, self::$results)) {
            self::$results[$extension] = array();
        }
        if (false == array_key_exists($check, self::$results[$extension])) {
            self::$results[$extension][$check] = array();
        }
        self::$results[$extension][$check]['comments'] = $comments;
    }
    public static function addComment($extension, $check, $comment)

    {
        if (false == array_key_exists($extension, self::$results)
            || false == array_key_exists($check, self::$results[$extension])
            || false == array_key_exists('comments', self::$results[$extension][$check])
        ) {
            self::$results[$extension][$check]['comments'] = array();
        }
        self::$results[$extension][$check]['comments'][] = $comment;
    }

    /**
     * set a result value
     *
     * @param string $extension
     * @param string $check
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public static function setResultValue($extension, $check, $name, $value)
    {
        if (false == array_key_exists($extension, self::$results)) {
            self::$results[$extension] = array();
        }
        if (false == array_key_exists($check, self::$results[$extension])) {
            self::$results[$extension][$check] = array();
        }
        if (false == array_key_exists('resultValue', self::$results[$extension][$check])) {
            self::$results[$extension][$check]['resultValue'] = array();
        }
        self::$results[$extension][$check]['resultValue'][$name] = $value;
    }

    public static function getFailedChecks($extension)
    {
        $failedChecks = array();
        foreach (self::$results[$extension] as $check=>$result) {
            if (array_key_exists('failed', $result) && $result['failed']) {
                $failedChecks[] = $check;
            }
        }
        return $failedChecks;
    }

    public static function getPassedChecks($extension)
    {
        $passedChecks = array();
        foreach (self::$results[$extension] as $check=>$result) {
            if (array_key_exists('failed', $result) && false == $result['failed']) {
                $passedChecks[] = $check;
            }
        }
        return $passedChecks;
    }


    /**
     * get result array
     */
    public static function getResultArray($extension)
    {
        $passedChecks = array();
        foreach (self::getPassedChecks($extension) as $check) {
            $passedChecks[$check] = array(
                'comments' => array()
            );
            if (array_key_exists('comments', self::$results[$extension][$check])) {
                $passedChecks[$check]['comments'] = self::$results[$extension][$check]['comments'];
            }
        }
        $failedChecks = array();
        foreach (self::getFailedChecks($extension) as $check) {
            $failedChecks[$check] = array(
                'comments' => array()
            );
            if (array_key_exists('comments', self::$results[$extension][$check])) {
                $failedChecks[$check]['comments'] = self::$results[$extension][$check]['comments'];
            }
        }

        return array(
            'passedChecks' => $passedChecks,
            'failedChecks' => $failedChecks,
        );
    }

    /**
     * get results array
     *
     * @param string $extension 
     * @param string $check
     * @return array
     */
    public static function getResults($extension, $check=null)
    {
        if (is_null($check)) {
            return self::$results[$extension];
        }
        if (array_key_exists($check, self::$results[$extension])) {
            return self::$results[$extension][$check];
        }
    }
    
    public static function getIssueResults($extension)
    {
        return IssueHandler::getResults($extension);
    }
    
}

?>