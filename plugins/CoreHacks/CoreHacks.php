<?php
namespace CoreHacks;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue;
use Netresearch\Plugin as Plugin;

/**
 * detect Magento core hacks
 */
class CoreHacks extends Plugin
{
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;

        $coreHackCount = 0;
        foreach (array('Mage_', 'Enterprise_') as $corePrefix) {
            $command = 'grep -rEh "class ' . $corePrefix . '.* extends" ' . $extensionPath;
            try {
                $output = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return;
            }
            $coreHackCount += count($output);
        }
        if ($coreHackCount != 0) {
            IssueHandler::addIssue(new Issue( array(
                "extension" =>  $this->_extensionPath,
                "checkname" =>  $this->_pluginName,
                "type"      =>  "corehack",
                "comment"   =>  "corehack found",
                "failed"    =>  true))
            );
        }
    }
}

