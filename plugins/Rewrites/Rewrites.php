<?php
namespace Rewrites;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin\Plugin as Plugin;

/**
 * count Magento core rewrites
 */
class Rewrites extends Plugin
{
    /**
     * Execute the Rewrites plugin
     */
    protected function _execute()
    {
        $command = sprintf('find "%s" -name config.xml', $this->_extensionPath);
        try {
            $configFiles = $this->_executeCommand($command);
        } catch (\Zend_Exception $e) {
            return;
        }

        $rewrites = array();
        $xpathFormat = '/config/global/%s//rewrite/..';
        $groupTypes = array('blocks', 'models', 'helpers');
        foreach ($configFiles as $configFile) {
            if (!($config = $this->_simplexml_load($configFile))) {
                continue;
            }
            foreach ($groupTypes as $groupType) {
                foreach ($config->xpath(sprintf($xpathFormat, $groupType)) as $groupRewrites) {
                    $group = $groupRewrites->getName();
                    foreach ($groupRewrites->rewrite->children() as $class => $rewrite) {
                        $classId = $group . '/' . $class;
                        $rewrites = array_merge_recursive($rewrites, array(
                            $this->_getIssueType($classId, $groupType) => array(
                                $groupType => array(
                                    array(
                                        'from' => $classId,
                                        'to'   => (string) $rewrite
                                    )
                                )
                            )
                        ));
                    }
                }
            }

            // controller rewrite
            $xpath = '/config/*[name()="frontend" or name()="admin"]/routers/*/args/modules/*[@before]';
            foreach ($config->xpath($xpath) as $rewrite) {
                $moduleName = $rewrite['before'];
                /** @var \SimpleXMLElement $area */
                list($area) = $rewrite ->xpath('ancestor::*[position()=5]');
                $rewrites = array_merge_recursive($rewrites, array(
                    $this->_getIssueType($moduleName, 'controller') => array(
                        'controller' => array(
                            array(
                                'from' => (string) $moduleName,
                                'to'   => (string) $rewrite,
                                'area' => $area->getName()
                            )
                        )
                    )
                ));
            }
        }

        // make critical issue to be first
        ksort($rewrites);
        foreach ($rewrites as $type => $groups) {
            foreach ($groups as $group => $items) {
                $comment = sprintf('Found %d %s %s(s):' . PHP_EOL,
                    count($items), rtrim($group, 's'), str_replace('_', ' ', $type));
                foreach ($items as $item) {
                    $comment .= self::OCCURRENCES_LIST_ITEM_PREFIX
                        . (isset($item['area']) ? sprintf('%s area - ', ucfirst($item['area'])) : '')
                        . sprintf('%s => %s', $item['from'], $item['to'])
                        . self::OCCURRENCES_LIST_ITEM_SUFFIX;
                }
                $this->_addIssue(array(
                    'type'        => $type,
                    'comment'     => trim($comment),
                    'occurrences' => count($items),
                ));
            }
        }
    }

    /**
     * @param string $groupType (model|block|helper|controller)
     * @param string $classId slash separated class identifier,
     *  ex. group/class or module name for controller
     * @return string 'critical_rewrite'|'rewrite'
     */
    protected function _getIssueType($classId, $groupType)
    {
        $critical = $this->_settings->critical->toArray();
        return (!empty($critical[$groupType])
            && is_array($critical[$groupType])
            && in_array($classId, $critical[$groupType]))
                ? 'critical_rewrite' : 'rewrite';
    }
}


