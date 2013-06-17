<?php
namespace SecurityCheck;

use Netresearch\Plugin\CodeSniffer as Plugin;

class SecurityCheck extends Plugin
{
    /**
     * Execution command
     * @var string
     */
    protected $_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';

    /**
     * Execute the SecurityCheck plugin
     */
    protected function _execute()
    {
        $this->_checkGlobalVariables();
        $this->_checkOutput();
        $this->_checkForSQLQueries();
        $this->_checkAvoidableFunctionsCalls();
    }
    
    /**
     * Check extension for not to have "echo", "print", "var_dump()", "var_export()" calls not in templates
     * Check extension for not to have "var_dump()" and "var_export()" calls in templates
     */
    protected function _checkOutput()
    {
        $options = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/OutputEchoPrint',
            'extensions' => 'php',
        );
        $csResults = $this->_executePhpCommand($options);
        $parsedNotTemplatesResult = $this->_parsePhpCsResult($csResults,
            'Output construction "%s" (allowed only in templates)',
            'OutputEchoPrint.UnescapedOutput.EchoPrint'
        );
        
        $options = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/Output',
            'extensions' => 'php,phtml',
        );        
        $csResults = $this->_executePhpCommand($options);
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
        $options = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/GlobalVariables',
            'extensions' => 'php,phtml',
        );
        $csResults = $this->_executePhpCommand($options);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Global variable %s',
            'GlobalVariables.GlobalVariables.GlobalVariables'
        );
        $this->_addPhpCsIssues($parsedResult, 'params');
    }

    /**
     * Check for SQL Queries (SELECT, INSERT, UPDATE, DELETE)
     */
    protected function _checkForSQLQueries()
    {
        $options = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/SQL',
            'extensions' => 'php',
            'ignore'     => '*/lib/*'
        );
        $csResults = $this->_executePhpCommand($options);
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
    protected function _checkAvoidableFunctionsCalls()
    {
        $options = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/Functions',
            'extensions' => 'php,phtml',
            'ignore'     => '*/lib/*'
        );
        $csResults = $this->_executePhpCommand($options);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            array(
                'Avoidable function "%s" call',
                'Functions.AvoidableCalls.IncludeRequire'   => 'Avoidable statement "%s" usage',
                'Functions.AvoidableCalls.RequireOnce'      => 'Avoidable statement "%s" usage',
            ),
            array(
                'Functions.AvoidableCalls.Config',
                'Functions.AvoidableCalls.Cookie',
                'Functions.AvoidableCalls.Eval',
                'Functions.AvoidableCalls.Header',
                'Functions.AvoidableCalls.IncludeRequire',
                'Functions.AvoidableCalls.Mail',
                'Functions.AvoidableCalls.Mysql',
                'Functions.AvoidableCalls.RequireOnce',
                'Functions.AvoidableCalls.Shell',
                'Functions.AvoidableCalls.Socket',
            )
        );
        $this->_addPhpCsIssues($parsedResult, 'avoidable_calls');
    }
}