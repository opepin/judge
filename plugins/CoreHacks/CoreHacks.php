<?php
namespace CoreHacks;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Plugin\CodeSniffer as Plugin;

/**
 * detect Magento core hacks
 */
class CoreHacks extends Plugin
{
    /**
     * Execution command
     * @var string
     */
    protected $_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';    
    
    /**
     * Execute the CoreHacks plugin (entry point)
     *
     * @param string $extensionPath the path to the extension to check
     * @throws \Exception
     */
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);
        $addionalParams = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/CoreHacks',
            'extensions' => 'php',
            'report'     => 'checkstyle',
        );
        $csResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Core Hack for class "%s"',
            array('CoreHacks.Class.Override')
        );
        $this->_addPhpCsIssues($parsedResult, 'corehack');
    }
}