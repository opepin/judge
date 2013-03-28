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
     * @return float the score for this test
     */
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
        $settings = $this->config->plugins->{$this->_pluginName};
        $score = $settings->good;
        if ($this->settings->allowedRequestParams < $this->checkForRequestParams($extensionPath)) {
            $score = $this->settings->bad;
        }
        if ($this->settings->allowedMissingEscaping < $this->checkForEscaping($extensionPath)) {
            $score = $this->settings->bad;
        }
        if ($this->settings->allowedSQLQueries < $this->checkForSQLQueries($extensionPath)) {
            $score = $this->settings->bad;
        }
        Logger::setScore($extensionPath, $this->_pluginName, $score);
        return $score;
    }


    /**
     *
     * @param string $extensionPath
     * @return int number of files containing direct usage of request params
     */
    protected function checkForRequestParams($extensionPath)
    {
        $foundTokens = 0;
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
                
                $foundTokens = $foundTokens + count($filesWithThatToken);
            }
            Logger::setResultValue($extensionPath, $this->_pluginName, $requestPattern, count($filesWithThatToken));
        }
        return $foundTokens;
    }


    /**
     *
     * @param string $extensionPath
     * @return int number of files containing unescaped output
     */
    protected function checkForEscaping($extensionPath)
    {
        $foundTokens = 0;
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
                
                $foundTokens = $foundTokens + count($filesWithThatToken);
            }
            Logger::setResultValue($extensionPath, $this->_pluginName, $unescapedOutputPattern, count($filesWithThatToken));
        }
        return $foundTokens;
    }

    /**
     *
     * @param type $extensionPath
     * @return int number of files containing direct usage of sql queries
     */
    protected function checkForSQLQueries($extensionPath)
    {
        $foundTokens = 0;
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
                
                $foundTokens = $foundTokens + count($filesWithThatToken);
            }
            Logger::setResultValue($extensionPath, $this->_pluginName, $sqlQueryPattern, count($filesWithThatToken));
        }
        return $foundTokens;
    }
}