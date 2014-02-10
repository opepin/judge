<?php
namespace Netresearch\Plugin;

use Netresearch\Config;
use Netresearch\IssueHandler;
use Netresearch\Issue;
use Netresearch\Logger;

/**
 * Base class for plugins
 */
abstract class Plugin
{
    const OCCURRENCES_LIST_ITEM_PREFIX = '  * ';
    const OCCURRENCES_LIST_ITEM_SUFFIX = PHP_EOL;

    /**
     * Execution command
     * @var string
     */
    protected $_execCommand;

    /**
     * Plugin name, same as check class name
     * @var string
     */
    protected $_pluginName;

    /**
     * Path to extension source
     * @var string
     */
    protected $_extensionPath;

    /**
     * The global Judge configuration
     * @var \Netresearch\Config
     */
    protected $_config;
    /**
     * The local plugin configuration
     * @var \Zend_Config
     */
    protected $_settings;

    /**
     * Base constructor for all plugins
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->_config = $config;
        $this->_pluginName = current(explode('\\', get_class($this)));
        $this->_settings = $this->_config->plugins->{$this->_pluginName};
    }

    /**
     * Execute a plugin (entry point)
     *
     * @param string $extensionPath the path to the extension to check
     */
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
        $this->_execute();
    }

    /**
     * Actual execution method, should be implemented in descendants
     *
     * @return void
     */
    abstract protected function _execute();

    /**
     * @param array $additionalOptions
     * @return array
     */
    protected function _executePhpCommand(array $additionalOptions)
    {
        exec('which php', $response);
        // error reporting E_ALL^E_NOTICE
        $command = reset($response) . ' -d error_reporting=30711';

        if (!empty($this->_config->phpOptions)) {
            foreach ($this->_config->phpOptions as $option) {
                $command .= ' -d ' . $option;
            }
        }

        $execCommand = $this->_execCommand;
        if (!empty($additionalOptions)) {
            foreach ($additionalOptions as $key => $value) {
                $execCommand .= is_string($key) ? ' --' . $key . '=' . $value
                    : ' ' . $value;
            }
        }

        $command .= ' ' . $execCommand;
        $command .= !in_array($this->_extensionPath, $additionalOptions) ? ' ' . $this->_extensionPath : '';

        return $this->_executeCommand($command);
    }

    /**
     * @param string $command
     * @return array
     * @throws \Zend_Exception
     */
    protected function _executeCommand($command)
    {
        //Logger::log($command);
        exec($command, $response, $status);

        if ($status == 255) {
            $this->setUnfinishedIssue();
            throw new \Zend_Exception('Failed to execute ' . $this->_pluginName .' plugin.' . $command);
        }

        return $response;
    }

    /**
     *
     */
    public function setUnfinishedIssue($reason = '')
    {
        $message = 'Failed to execute ' . $this->_pluginName . ' plugin.';
        // if a specific reason is given, append it to the message
        if (trim($reason)) {
            $message .= ' Reason: ' . $reason;
        }
        $this->_addIssue(array(
            'type'    => 'unfinished',
            'comment' => $message,
        ));
    }

    /**
     * @param $command
     * @return Plugin
     */
    public function setExecCommand($command)
    {
        $this->_execCommand = $command;
        return $this;
    }

    /**
     * Adds issue to result with specified type, comment, [files] and [occurrences]
     *
     * @param array $issue
     * @throws \Zend_Exception
     */
    protected function _addIssue(array $issue)
    {
        if (!isset($issue['comment']) || empty($issue['type'])) {
            $error = 'Attempt to add malformed issue';
            $this->_logWithBackTrace($error);
            throw new \Zend_Exception($error);
        }

        IssueHandler::addIssue(new Issue(array_merge(
            array(
                'extension'   => $this->_extensionPath,
                'checkname'   => $this->_pluginName,
                'files'       => array(),
                'occurrences' => 1,
            ),
            $issue
        )));
    }

    /**
     * Load simple xml safely and log errors if occurred
     *
     * @param string $xml
     * @return \SimpleXMLElement
     */
    protected function _simplexml_load($xml)
    {
        libxml_use_internal_errors(true);
        if (!($xml = simplexml_load_string($xml))) {
            $errors = 'Failed loading XML';
            foreach(libxml_get_errors() as $error) {
                $errors .= PHP_EOL . "\t* " . trim($error->message);
            }
            $this->_logWithBackTrace($errors);
        }
        return $xml;
    }

    /**
     * Write log message with backtrace
     *
     * @param string $message
     */
    protected function _logWithBackTrace($message = '')
    {
        try {
            throw new \Exception();
        } catch (\Exception $e) {
            /**
             * the best approach to get user friendly backtrace
             * debug_backtrace() and debug_print_backtrace() are horrible
             */
            $message .= PHP_EOL . 'Back trace:' . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
        }
        Logger::log($message);
    }
}
