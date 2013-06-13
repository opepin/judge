<?php
namespace SecurityCheck;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Plugin\CodeSniffer as Plugin;

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
        $this->_checkDangerousFunctions();
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
            'Output construction "%s" (allowed only in templates)',
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
            array('Output.Dump.VarDump', 'Output.Dump.VarExportPrintR', 'Output.Dump.ZendDebug')
        );
        $parsedResult = array_merge($parsedNotTemplatesResult, $parsedTemplatesResult);
        $this->_addPhpCsIssues($parsedResult, 'escape');
    }
    
    /**
     * Check extension for not to have global variables calls
     */    
    protected function _checkGlobalVariables()
    {
        $addionalParams = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/GlobalVariables',
            'extensions' => 'php,phtml',
            'report'     => 'checkstyle',
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Global variable %s',
            array('GlobalVariables.GlobalVariables.GlobalVariables')
        );
        $this->_addPhpCsIssues($parsedResult, 'params');
    }

    /**
     * Check for SQL Queries (SELECT, INSERT, UPDATE, DELETE)
     */
    protected function _checkForSQLQueries()
    {
        $addionalParams = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/SQL',
            'extensions' => 'php',
            'report'     => 'checkstyle',
            'ignore'     => '*/lib/*'
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Raw %s query',
            array('SQL.RawSQL.Select', 'SQL.RawSQL.Delete', 'SQL.RawSQL.Update', 'SQL.RawSQL.Insert')
        );
        $this->_addPhpCsIssues($parsedResult, 'sql');
    }
    
    /**
     * Check for dangerous functions usage
     *  - shell interactions
     */
    protected function _checkDangerousFunctions()
    {
        $addionalParams = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/DangerousFunctions',
            'extensions' => 'php,phtml',
            'report'     => 'checkstyle',
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Dangerous function "%s" call',
            array('DangerousFunctions.DangerousFunctions.Config',
                'DangerousFunctions.DangerousFunctions.Cookie',
                'DangerousFunctions.DangerousFunctions.Eval',
                'DangerousFunctions.DangerousFunctions.Header',
                'DangerousFunctions.DangerousFunctions.Mail',
                'DangerousFunctions.DangerousFunctions.Shell',
                'DangerousFunctions.DangerousFunctions.Socket',
            )
        );
        $this->_addPhpCsIssues($parsedResult, 'bad_functions');        
    }
}