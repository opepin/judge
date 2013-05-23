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

        foreach ($this->rewrites as $rewrite) {
            list($type, $code) = explode('s:', $rewrite);
            $typePrefix = $this->isCritical($rewrite) ? 'critical_' : '';
                IssueHandler::addIssue(new Issue( array( 
                    "extension" =>  $extensionPath,
                    "checkname" => $this->_pluginName,
                    "type"      => $typePrefix . $type . '_rewrite',
                    "comment"   => $code,
                    "failed"    =>  true
                )));
        }
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


