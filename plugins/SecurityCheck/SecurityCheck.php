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
            'report'     => 'checkstyle',
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedNotTemplatesResult = $this->_parsePhpCsResult($csResults,
            'Output construction "%s" found not in templates;',
            array('OutputEchoPrint.UnescapedOutput.EchoPrint')
        );
        
        $addionalParams = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/Output',
            'extensions' => 'php,phtml',
            'report'     => 'checkstyle',
        );        
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedTemplatesResult = $this->_parsePhpCsResult($csResults,
            'Output construction %s',
            array('Output.UnescapedOutput.VarDumpExport')
        );
        
        $parsedResult = array_merge($parsedNotTemplatesResult, $parsedTemplatesResult);
        foreach ($parsedResult as $entry) {
            IssueHandler::addIssue(new Issue( array( 
                "extension"   => $this->_extensionPath,
                "checkname"   => $this->_pluginName,
                "type"        => 'escape',
                "comment"     => $entry['comment'],
                "files"       => $entry['files'],
                "occurrences" => $entry['occurrences'],
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
            'report'   => 'checkstyle',
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Global variable %s',
            array('GlobalVariables.GlobalVariables.GlobalVariables')
        );
        foreach ($parsedResult as $entry) {
            IssueHandler::addIssue(new Issue( array( 
                "extension"   => $this->_extensionPath,
                "checkname"   => $this->_pluginName,
                "type"        => 'params',
                "comment"     => $entry['comment'],
                "files"       => $entry['files'],
                "occurrences" => $entry['occurrences'],
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
     * Parses php code sniffer execution xml into array with predifined structure
     * 
     * @param array  $phpcsOutput
     * @param string $comment
     * @param array $sniffsToListen
     * @return array 
     */    
    protected function _parsePhpCsResult($phpcsOutput, $comment = 'Issue %s found', $sniffsToListen = array())
    {
        $result = array();
        if (!is_array($sniffsToListen) || empty($sniffsToListen)) {
            return $result;
        }
        $phpcsOutput = implode('', $phpcsOutput);
        
        try {
            $xml = simplexml_load_string($phpcsOutput);
        } catch(Exception $e) {
            return $result;
        }
        $files = $xml->xpath('file');
        if (!$files) {
            return $result;
        }
        foreach ($files as $file) {
            $filename = (string)$file->attributes()->name;
            $errors = $file->xpath('error');
            if (!$errors) {
                continue;
            }
            foreach ($errors as $error) {
                // Ignoring all sniffs except specified in $sniffsToListen
                if (!in_array((string)$error->attributes()->source, $sniffsToListen)) {
                    continue;
                }
                $type = (string)$error->attributes()->message;
                $lineNumber = (string)$error->attributes()->line;
                if (!array_key_exists($type, $result)) {
                    $result[$type] = array();
                }
                $result[$type][] = $filename . ':' . $lineNumber;
            }
        }
        
        $return = array();
        foreach ($result as $type => $files) {
            $occurences = count($files);
            $files = array_unique($files);
            sort($files);
            $return[] = array(
                'files'       => $files,
                'comment'     => sprintf($comment, $type),
                'occurrences' => $occurences,
            );
        }
        return $return;
    }
}