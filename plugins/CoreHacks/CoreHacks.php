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
        $this->_checkCoreClasses();
        $this->_checkCoreCodePool();
    }
    
    /**
     * Looks for core classes overrides
     */
    protected function _checkCoreClasses()
    {
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
    
    /**
     * Looks for files in "core" code pool
     */
    protected function _checkCoreCodePool()
    {
        $files = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->_extensionPath));

        while($iterator->valid()) {
            if (!$iterator->isDot()) {
                if (strpos($iterator->getSubPath(), 'app/code/core') !== false) {
                    $files[] = $iterator->getSubPathName();
                }
            }
            $iterator->next();
        }
        if (!empty($files)) {
            IssueHandler::addIssue(new Issue( array( 
                "extension"   => $this->_extensionPath,
                "checkname"   => $this->_pluginName,
                "type"        => 'corehack',
                "comment"     => 'Core Hacks for "core" code pool',
                "files"       => $files,
                "occurrences" => count($files),
            )));
        }
    }
}