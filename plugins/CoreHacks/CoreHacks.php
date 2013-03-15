<?php
namespace CoreHacks;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue;
use Netresearch\PluginInterface as JudgePlugin;

/**
 * detect Magento core hacks
 */
class CoreHacks implements JudgePlugin
{
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function execute($extensionPath)
    {
        $settings = $this->config->plugins->CoreHacks;

        $coreHackCount = 0;
        foreach (array('Mage_', 'Enterprise_') as $corePrefix) {
            $command = 'grep -rEh "class ' . $corePrefix . '.* extends" ' . $extensionPath;
            exec($command, $output, $return);
            $coreHackCount += count($output);
        }
        if (0 == $coreHackCount) {
            Logger::setScore($extensionPath, current(explode('\\', __CLASS__)), $settings->good);
            return $settings->good;
        }
        Logger::setScore($extensionPath, current(explode('\\', __CLASS__)), $settings->bad);
        IssueHandler::addIssue(new Issue(
                array(  "extension" =>  $this->extensionPath,
                        "checkname" =>  current(explode('\\', __CLASS__)),
                        "type"      =>  "corehack",
                        "comment"   =>  "corehack found",
                        "failed"    =>  true)));
        return $settings->bad;
    }
}

