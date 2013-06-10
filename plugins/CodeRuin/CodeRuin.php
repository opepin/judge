<?php
namespace CodeRuin;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Plugin\CodeSniffer as Plugin;

class CodeRuin extends Plugin
{
    /**
     * Execution command
     * @var string
     */
    protected $_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';

    /**
     * Execute the CodeRuin plugin (entry point)
     *
     * @param string $extensionPath the path to the extension to check
     * @throws \Exception
     */
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);
        $this->_checkComments();
        $this->_checkDieCall();
    }

    /**
     * Check for fix requests '@todo', '@fixme', '@xxx' in comments
     */
    protected function _checkComments()
    {
        $addionalParams = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/Comments',
            'extensions' => 'php,phtml',
            'report'     => 'checkstyle',
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Fix request "%s"',
            array('Comments.Comments.FixRequest')
        );
        $this->_addPhpCsIssues($parsedResult, 'warning');
    }
    
    /**
     * Check for "die()" function call
     */
    protected function _checkDieCall()
    {    
        $addionalParams = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/DieExit',
            'extensions' => 'php,phtml',
            'report'     => 'checkstyle',
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Function "%s"',
            array('DieExit.DieExit.DieExit')
        );
        $this->_addPhpCsIssues($parsedResult, 'critical');
    }
}