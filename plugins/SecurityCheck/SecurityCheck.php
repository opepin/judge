<?php
namespace SecurityCheck;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;
use Netresearch\Plugin as Plugin;

class SecurityCheck extends Plugin implements JudgePlugin
{
    protected $config;
    protected $settings;
    protected $results;

    /**
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->_pluginName   = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->_pluginName};
    }

    /**
     *
     * @param type $extensionPath
     */
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
        $settings = $this->config->plugins->{$this->_pluginName};
        $this->checkForRequestParams($extensionPath);
        $this->checkForEscaping($extensionPath);
        $this->checkForSQLQueries($extensionPath);
    }


    /**
     *
     * @param string $extensionPath
     * @return int number of files containing direct usage of request params
     */
    protected function checkForRequestParams($extensionPath)
    {
        foreach ($this->settings->requestParamsPattern as $requestPattern) {
            $command = 'grep -riEl "' . $requestPattern . '" ' . $extensionPath . '/app';
            try {
                $filesWithThatToken = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return $this->settings->unfinished;
            }
            if (0 < count($filesWithThatToken)) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->_pluginName,
                                "type"      => 'params',
                                "comment"   => $requestPattern,
                                "files"     => $filesWithThatToken,
                                "failed"    =>  true)));
                
            }
            Logger::setResultValue($extensionPath, $this->_pluginName, $requestPattern, count($filesWithThatToken));
        }
    }


    /**
     *
     * @param string $extensionPath
     */
    protected function checkForEscaping($extensionPath)
    {
        foreach ($this->settings->unescapedOutputPattern as $unescapedOutputPattern) {
            $command = 'grep -riEl "' . $unescapedOutputPattern . '" ' . $extensionPath . '/app';
            try {
                $filesWithThatToken = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return $this->settings->unfinished;
            }
            if (0 < count($filesWithThatToken)) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->_pluginName,
                                "type"      => 'escape',
                                "comment"   => $unescapedOutputPattern,
                                "files"     => $filesWithThatToken,
                                "failed"    =>  true)));
                
            }
            Logger::setResultValue($extensionPath, $this->_pluginName, $unescapedOutputPattern, count($filesWithThatToken));
        }
    }

    /**
     *
     * @param type $extensionPath
     */
    protected function checkForSQLQueries($extensionPath)
    {
        foreach ($this->settings->sqlQueryPattern as $sqlQueryPattern) {
            $command = 'grep -riEl "' . $sqlQueryPattern . '" ' . $extensionPath . '/app';
            try {
                $filesWithThatToken = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return $this->settings->unfinished;
            }
            if (0 < count($filesWithThatToken)) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->_pluginName,
                                "type"      => 'sql',
                                "comment"   => $sqlQueryPattern,
                                "files"     => $filesWithThatToken,
                                "failed"    =>  true)));
                
            }
            Logger::setResultValue($extensionPath, $this->_pluginName, $sqlQueryPattern, count($filesWithThatToken));
        }
    }
}