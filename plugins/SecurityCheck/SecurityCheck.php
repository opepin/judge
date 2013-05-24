<?php
namespace SecurityCheck;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin as Plugin;

class SecurityCheck extends Plugin
{
    protected $_results;

    /**
     *
     * @param type $extensionPath
     */
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);
        $this->_checkForRequestParams($extensionPath);
        $this->_checkForEscaping($extensionPath);
        $this->_checkForSQLQueries($extensionPath);
    }


    /**
     *
     * @param string $extensionPath
     * @return int number of files containing direct usage of request params
     */
    protected function _checkForRequestParams($extensionPath)
    {
        foreach ($this->_settings->requestParamsPattern as $requestPattern) {
            $command = 'grep -riEl "' . $requestPattern . '" ' . $extensionPath . '/app';
            try {
                $filesWithThatToken = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return;
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
    protected function _checkForEscaping($extensionPath)
    {
        foreach ($this->_settings->unescapedOutputPattern as $unescapedOutputPattern) {
            $command = 'grep -riEl "' . $unescapedOutputPattern . '" ' . $extensionPath . '/app';
            try {
                $filesWithThatToken = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return;
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
    protected function _checkForSQLQueries($extensionPath)
    {
        foreach ($this->_settings->sqlQueryPattern as $sqlQueryPattern) {
            $command = 'grep -riEl "' . $sqlQueryPattern . '" ' . $extensionPath . '/app';
            try {
                $filesWithThatToken = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return;
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