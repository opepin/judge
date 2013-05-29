<?php
namespace SecurityCheck;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin as Plugin;

class SecurityCheck extends Plugin
{
    /**
     * Execution command
     * @var string
     */
    protected $_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';

    /**
     * Execute the SecurityCheck plugin (entry point)
     *
     * @param string $extensionPath the path to the extension to check
     * @throws \Exception
     */
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);
        $this->_checkGlobalVariables();
        $this->_checkOutput();
        $this->_checkForSQLQueries();
    }
    
    /**
     * Check extension for not to have "echo", "print", "var_dump()", "var_export()" calls not in templates
     * Check extension for not to have "var_dump()" and "var_export()" calls in templates
     */
    protected function _checkOutput()
    {
        $addionalParams = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/OutputEchoPrint',
            'extensions' => 'php',
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedNotTemplatesResult = $this->_parsePhpCsResult($csResults);
        
        $addionalParams = array(
            'standard' => __DIR__ . '/CodeSniffer/Standards/Output',
            'extensions' => 'php,phtml',
        );        
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedTemplatesResult = $this->_parsePhpCsResult($csResults);
        
        $parsedResult = array_merge($parsedNotTemplatesResult, $parsedTemplatesResult);
        $failed = count($parsedResult) > $this->_settings->allowedMissingEscaping ? true : false;
        foreach ($parsedResult as $comment) {
            IssueHandler::addIssue(new Issue( array( 
                "extension" => $this->_extensionPath,
                "checkname" => $this->_pluginName,
                "type"      => 'escape',
                "comment"   => $comment,
                "failed"    => $failed,
            )));
        }
    }
    
    /**
     * Check extension for not to have global variables calls
     */    
    protected function _checkGlobalVariables()
    {
        $addionalParams = array(
            'standard' => __DIR__ . '/CodeSniffer/Standards/GlobalVariables',
            'extensions' => 'php,phtml',
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedResult = $this->_parsePhpCsResult($csResults);
        $failed = count($parsedResult) > $this->_settings->allowedRequestParams ? true : false;
        foreach ($parsedResult as $comment) {
            IssueHandler::addIssue(new Issue( array( 
                "extension" => $this->_extensionPath,
                "checkname" => $this->_pluginName,
                "type"      => 'params',
                "comment"   => $comment,
                "failed"    => $failed,
            )));
        }        
    }

    /**
     * Check for SQL Queries (SELECT, INSERT, UPDATE, DELETE)
     */
    protected function _checkForSQLQueries()
    {
        foreach ($this->_settings->sqlQueryPattern as $sqlQueryPattern) {
            $command = 'grep -riEl "' . $sqlQueryPattern . '" ' . $this->_extensionPath . '/app';
            try {
                $filesWithThatToken = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return;
            }
            if (0 < count($filesWithThatToken)) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" => $this->_extensionPath,
                                "checkname" => $this->_pluginName,
                                "type"      => 'sql',
                                "comment"   => $sqlQueryPattern,
                                "files"     => $filesWithThatToken,
                                "failed"    =>  true)));
                
            }
            Logger::setResultValue($this->_extensionPath, $this->_pluginName, $sqlQueryPattern, count($filesWithThatToken));
        }
    }
    
    /**
     * Parses php code sniffer execution resource into array with predifined structure
     * 
     * @param array $phpcsOutput
     * @return array 
     */
    protected function _parsePhpCsResult($phpcsOutput)
    {
        $result = array();
        foreach ($phpcsOutput as $string) {
            if (strstr($string, 'FILE:')) {
                $fileName = ltrim(str_replace('FILE:', '', $string));
                continue;
            }
            if (strstr($string, ' ERROR ')) {
                $exploded = explode('|', $string);
                $result[] = $fileName . ' ' . end($exploded);
            }
        }
        return $result;
    }
}