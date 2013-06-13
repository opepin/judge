<?php
namespace CoreHacks;

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
     * Execute the CoreHacks plugin
     */
    protected function _execute()
    {
        $this->_checkCoreClasses();
        $this->_checkCoreCodePool();
    }
    
    /**
     * Looks for core classes overrides in local and community pools
     */
    protected function _checkCoreClasses()
    {
        $options = array(
            'standard'   => __DIR__ . '/CodeSniffer/Standards/CoreHacks',
            'extensions' => 'php',
            'ignore'     => '*/app/code/core/*',
        );
        $csResults = $this->_executePhpCommand($options);
        $parsedResult = $this->_parsePhpCsResult($csResults,
            'Class "%s" has a forbidden namespace',
            'CoreHacks.Class.Override'
        );
        $this->_addPhpCsIssues($parsedResult, 'corehack');
    }
    
    /**
     * Looks for files in "core" code pool
     */
    protected function _checkCoreCodePool()
    {
        $files = array();
        /** @var \RecursiveDirectoryIterator $dir */
        $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->_extensionPath));

        while ($dir->valid()) {
            if (!$dir->isDot() && strpos($dir->getSubPath(), 'app/code/core') !== false) {
                $files[] = $dir->getSubPathname();
            }
            $dir->next();
        }
        if (!empty($files)) {
            $this->_addIssue(array(
                'type'        => 'corehack',
                'comment'     => 'Usage of "core" code pool',
                'files'       => $files,
                'occurrences' => count($files),
            ));
        }
    }
}