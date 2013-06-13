<?php
namespace CodeRuin;

use Netresearch\Plugin\CodeSniffer as Plugin;

class CodeRuin extends Plugin
{
    /**
     * Execution command
     * @var string
     */
    protected $_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';

    /**
     * Execute the CodeRuin plugin
     */
    protected function _execute()
    {
        $this->_checkComments();
        $this->_checkDieCall();
    }

    /**
     * Check for fix requests '@todo', '@fixme', '@xxx' in comments
     */
    protected function _checkComments()
    {
        $options = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/Comments',
            'extensions' => 'php,phtml',
        );
        $csResults = $this->_executePhpCommand($options);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Fix request "%s"',
            'Comments.Comments.FixRequest'
        );
        $this->_addPhpCsIssues($parsedResult, 'warning');
    }
    
    /**
     * Check for "die()" function call
     */
    protected function _checkDieCall()
    {    
        $options = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/DieExit',
            'extensions' => 'php,phtml',
        );
        $csResults = $this->_executePhpCommand($options);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Function "%s"',
            'DieExit.DieExit.DieExit'
        );
        $this->_addPhpCsIssues($parsedResult, 'critical');
    }
}