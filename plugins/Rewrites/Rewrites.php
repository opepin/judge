<?php
namespace Rewrites;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;
use Netresearch\Plugin as Plugin;

/**
 * count Magento core rewrites
 */
class Rewrites extends Plugin implements JudgePlugin
{
    protected $config;
    protected $extensionPath;
    protected $settings;
    protected $rewrites=array();

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->_pluginName   = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->_pluginName};
    }

    public function execute($extensionPath)
    {
        $score = 0;
        $this->_extensionPath = $extensionPath;

        $command = sprintf('find "%s" -name config.xml', $extensionPath);
        try {
            $configFiles = $this->_executeCommand($command);
        } catch (\Zend_Exception $e) {
            return $this->settings->unfinished;
        }

        $types = array('blocks', 'models');
        foreach ($configFiles as $configFile) {
            foreach ($types as $type) {
                $this->findRewrites($configFile, $type);
            }
        }

        if (count($this->rewrites) <= $this->settings->allowedRewrites->count) {
            $score += $this->settings->allowedRewrites->good;
        } elseif ($this->settings->maxRewrites->count < count($this->rewrites)) {
            $score += $this->settings->maxRewrites->good;
        } else {
            $score += $this->settings->maxRewrites->bad;
        }
        foreach ($this->rewrites as $rewrite) {
            list($type, $code) = explode('s:', $rewrite);
            if ($this->isCritical($rewrite)) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->_pluginName,
                                "type"      => 'critical_' . $type . '_rewrite',
                                "comment"   => $code,
                                "failed"    =>  true)));
                
                $score += $this->settings->critical->bad;
            } else {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->_pluginName,
                                "type"      => $type . '_rewrite',
                                "comment"   => $code,
                                "failed"    =>  true)));
            }
        }

        Logger::setScore($extensionPath, $this->_pluginName, $score);
        return $score;
    }

    protected function findRewrites($configFile, $type)
    {
        $xpath = '/config/global/' . $type . '//rewrite/..';
        $config = simplexml_load_file($configFile);
        foreach ($config->xpath($xpath) as $moduleRewrites) {
            $module = $moduleRewrites->getName();
            foreach ($moduleRewrites->rewrite->children() as $path=>$class) {
                $this->rewrites[] = $type . ':' . $module . '/' . $path;
            }
        }
    }

    protected function isCritical($rewrite)
    {
        $critical = $this->settings->critical->toArray();
        list($type, $code) = explode(':', $rewrite);
        if (false == is_array($critical[$type])) {
            return false;
        }
        return in_array($code, $critical[$type]);
    }
}


