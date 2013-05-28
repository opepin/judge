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
        $this->_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';
    }

    /**
     *
     * @param type $extensionPath
     */
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
        $settings = $this->config->plugins->{$this->_pluginName};
        $this->_checkGlobalVariables($extensionPath);
        $this->_checkOutput($extensionPath);
        $this->_checkForSQLQueries($extensionPath);
    }
    
    /**
     * Check extension for not to have "echo", "print", "var_dump()", "var_export()" calls not in templates
     * Check extension for not to have "var_dump()" and "var_export()" calls in templates
     * 
     * @param string $extensionPath 
     */
    protected function _checkOutput($extensionPath)
    {
        $addionalParams = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/OutputEchoPrint',
            'extensions' => 'php',
        );
        $csResults = $this->_executePhpCommand($this->config, $addionalParams);
        $parsedNotTemplatesResult = $this->_parsePhpCsResult($csResults);
        
        $addionalParams = array(
            'standard' => __DIR__ . '/CodeSniffer/Standards/Output',
            'extensions' => 'php,phtml',
        );        
        $csResults = $this->_executePhpCommand($this->config, $addionalParams);
        $parsedTemplatesResult = $this->_parsePhpCsResult($csResults);
        
        $parsedResult = array_merge($parsedNotTemplatesResult, $parsedTemplatesResult);
        $failed = count($parsedResult) > $this->settings->allowedMissingEscaping ? true : false;
        foreach ($parsedResult as $comment) {
            IssueHandler::addIssue(new Issue( array( 
                "extension" => $extensionPath,
                "checkname" => $this->_pluginName,
                "type"      => 'escape',
                "comment"   => $comment,
                "failed"    => $failed,
            )));
        }
    }
    
    /**
     * Check extension for not to have global variables calls
     * 
     * @param string $extensionPath 
     */    
    protected function _checkGlobalVariables($extensionPath)
    {
        $addionalParams = array(
            'standard' => __DIR__ . '/CodeSniffer/Standards/GlobalVariables',
            'extensions' => 'php,phtml',
        );
        $csResults = $this->_executePhpCommand($this->config, $addionalParams);
        $parsedResult = $this->_parsePhpCsResult($csResults);
        $failed = count($parsedResult) > $this->settings->allowedRequestParams ? true : false;
        foreach ($parsedResult as $comment) {
            IssueHandler::addIssue(new Issue( array( 
                "extension" => $extensionPath,
                "checkname" => $this->_pluginName,
                "type"      => 'params',
                "comment"   => $comment,
                "failed"    => $failed,
            )));
        }        
    }

    /**
     *
     * @param type $extensionPath
     */
    protected function _checkForSQLQueries($extensionPath)
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
    
    /**
     * Parses php code sniffer execution resource into array with predifined structure
     * 
     * @param string $phpcsOutput
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