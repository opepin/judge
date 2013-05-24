<?php
namespace Rewrites;

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
    /**
     * @param $extensionPath
     */
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);

        $command = sprintf('find "%s" -name config.xml', $extensionPath);
        try {
            $configFiles = $this->_executeCommand($command);
        } catch (\Zend_Exception $e) {
            return;
        }

        $types = array('blocks', 'models');
        $rewrites = array();
        foreach ($configFiles as $configFile) {
            $config = simplexml_load_file($configFile);
            foreach ($types as $type) {
                $rewrites = array_merge($rewrites, $this->_findRewrites($config, $type));
            }
        }

        foreach ($rewrites as $rewrite) {
            list($type, $code) = explode('s:', $rewrite);
            $typePrefix = $this->_isCritical($rewrite) ? 'critical_' : '';
            IssueHandler::addIssue(new Issue(array(
                "extension" => $extensionPath,
                "checkname" => $this->_pluginName,
                "type"      => $typePrefix . $type . '_rewrite',
                "comment"   => $code,
                "failed"    => true
            )));
        }
    }

    /**
     * @param \SimpleXMLElement $config
     * @param string $type
     * @return array
     */
    protected function _findRewrites($config, $type)
    {
        $xpath = '/config/global/' . $type . '//rewrite/..';
        $rewrites = array();
        foreach ($config->xpath($xpath) as $moduleRewrites) {
            $module = $moduleRewrites->getName();
            foreach ($moduleRewrites->rewrite->children() as $path => $class) {
                $rewrites[] = $type . ':' . $module . '/' . $path;
            }
        }
        return $rewrites;
    }

    /**
     * @param $rewrite
     * @return bool
     */
    protected function _isCritical($rewrite)
    {
        $critical = $this->_settings->critical->toArray();
        list($type, $code) = explode(':', $rewrite);
        return !empty($critical[$type])
            && is_array($critical[$type])
            && in_array($code, $critical[$type]);
    }
}


