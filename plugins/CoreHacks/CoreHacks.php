<?php
namespace CoreHacks;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue;
use Netresearch\PluginInterface as JudgePlugin;
use Netresearch\Plugin as Plugin;

/**
 * detect Magento core hacks
 */
class CoreHacks extends Plugin implements JudgePlugin
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->_pluginName = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->_pluginName};
    }

    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;

        $coreHackCount = 0;
        foreach (array('Mage_', 'Enterprise_') as $corePrefix) {
            $command = 'grep -rEh "class ' . $corePrefix . '.* extends" ' . $extensionPath;
            try {
                $output = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return $this->settings->unfinished;
            }
            $coreHackCount += count($output);
        }
        if (0 == $coreHackCount) {
            Logger::setScore($extensionPath, $this->_pluginName, $this->settings->good);
            return $this->settings->good;
        }
        Logger::setScore($extensionPath, $this->_pluginName, $this->settings->bad);
        IssueHandler::addIssue(new Issue(
                array(  "extension" =>  $this->extensionPath,
                        "checkname" =>  $this->_pluginName,
                        "type"      =>  "corehack",
                        "comment"   =>  "corehack found",
                        "failed"    =>  true)));
        return $this->settings->bad;
    }
}

