<?php
namespace MageCompatibility;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue;
use Netresearch\Plugin as Plugin;

use \dibi as dibi;

class MageCompatibility extends Plugin
{
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);
        try{
            $this->_connectTagDatabase();

            $availableVersions = dibi::query('SELECT concat( m.edition, " ", m.version ) as Magento FROM magento m ORDER BY Magento')->fetchPairs();
            $supportedVersions = array();

            $extension = new Extension($this->_extensionPath);
            $methods = $extension->getUsedMagentoMethods();
            $classes = $extension->getUsedMagentoClasses();
            
            IssueHandler::addIssue(new Issue(
                    array(  "extension" =>  $extensionPath,
                            "checkname" => $this->_pluginName,
                            "type"      => 'mage_compatibility',
                            "comment"   => sprintf('Extension uses %d classes and %d methods of Magento core',$classes->count(),$methods->count()),
                            "failed"    =>  true)));

            $incompatibleVersions = array();
            foreach ($availableVersions as $version) {
                $incompatibleVersions[$version] = array(
                    'classes'   => array(),
                    'methods'   => array(),
                    'constants' => array()
                );
            }
            foreach ($classes as $class) {
                $class->setConfig($this->_settings);
                $supportedVersions = $class->getMagentoVersions();
                if (is_array($supportedVersions)) {
                    $tagIncompatibleVersions = array_diff($availableVersions, $supportedVersions);
                    foreach ($tagIncompatibleVersions as $version) {
                        $incompatibleVersions[$version]['classes'][] = $class->getName();
                    }
                }
            }
            foreach ($methods as $method) {
                $isExtensionMethod = false;
                $context = current($method->getContext());
                $method->setConfig($this->_settings);
                $supportedVersions = $method->getMagentoVersions();
                //echo $context['class'] . '->' . $method->getName() . ' ';
                if (false == is_array($supportedVersions)) {
                    continue;
                }
                $tagIncompatibleVersions = array_diff($availableVersions, $supportedVersions);
                foreach ($tagIncompatibleVersions as $version) {
                    $methodName = $method->getContext('class')
                        . '->' . $method->getName()
                        . '(' . implode(', ', $method->getParams()) . ')';
                    if ($extension->hasMethod($method->getName())) {
                        $methodName .= ' [maybe part of the extension]';
                        continue;
                    }
                    $incompatibleVersions[$version]['methods'][] = $methodName;
                }
            }

            $compatibleVersions = array();

            foreach ($incompatibleVersions as $version=>$incompatibilities) {
                $message = '';
                $incompatibleClasses   = array_unique($incompatibilities['classes']);
                $incompatibleMethods   = array_unique($incompatibilities['methods']);
                $incompatibleConstants = array_unique($incompatibilities['constants']);
                if (0 < count($incompatibleClasses)) {
                    $message .= sprintf(
                        "<comment>The following classes are not compatible to Magento %s:</comment>\n  * %s\n",
                        $version,
                        implode("\n  * ", $incompatibleClasses)
                    );
                }
                if (0 < count($incompatibleMethods)) {
                    $message .= sprintf(
                        "<comment>The following methods are not compatible to Magento %s:</comment>\n  * %s\n",
                        $version,
                        implode("\n  * ", $incompatibleMethods)
                    );
                }
                if (0 < count($incompatibleConstants)) {
                    $message .= sprintf(
                        "<comment>The following constants are not compatible to Magento %s:</comment>\n  * %s\n",
                        $version,
                        implode("\n  * ", $incompatibleConstants)
                    );
                }
                if (0 < strlen($message)) {
                    IssueHandler::addIssue(new Issue(
                            array(  "extension" => $extensionPath ,"checkname" => $this->_pluginName,
                                    "type"      => 'mage_compatibility',
                                    "comment"   => sprintf("<error>Extension is not compatible to Magento %s</error>\n%s", $version, $message),
                                    "failed"    =>  true)));
                } else {
                    $compatibleVersions[] = $version;
                }
            }
            IssueHandler::addIssue(new Issue(
                    array(  "extension" => $extensionPath ,"checkname" => $this->_pluginName,
                            "type"      => 'mage_compatibility',
                            "comment"   => 'Checked Magento versions: ' . implode(', ', $availableVersions) . "\n"
                            . '* Extension seems to support following Magento versions: ' . implode(', ', $compatibleVersions),
                            "failed"    =>  false)));

            foreach (array_keys($incompatibleVersions) as $key) {
                if (0 == count($incompatibleVersions[$key]['classes']) &&
                    0 == count($incompatibleVersions[$key]['methods']) &&
                    0 == count($incompatibleVersions[$key]['constants'])
                    ) {
                    unset($incompatibleVersions[$key]);
                }
            }
            if ($this->_containsNoLatestVersion(array_keys($incompatibleVersions), 'CE')) {
                IssueHandler::addIssue(new Issue( array(
                    "extension" => $extensionPath ,"checkname" => $this->_pluginName,
                    "type"      => 'mage_compatibility',
                    "comment"   => sprintf('Extension supports Magento at least from CE version %s and EE version %s',
                     $this->_settings->min->ce, $this->_settings->min->ee),
                    "failed"    =>  false
                )));
                return ;
            }
        } catch (\Exception $e) {
            $this->setUnfinishedIssue();
            $message = is_array($e->getMessage()) ? $e->getMessage() : array($e->getMessage());
            Logger::error(implode(PHP_EOL, $message), array(), false);
            return ;
        }
    }

    protected function _getTagFileNames()
    {
        return glob(__DIR__ . '/var/tags/*');
    }

    protected function _getEdition($tagFileName)
    {
        list($edition, $version) = explode('-', baseName($tagFileName));
        return ucfirst(substr($edition, 0, 1)) . 'E';
    }

    protected function _getVersion($tagFileName)
    {
        $basename = strstr(basename($tagFileName), '.tags', $beforeNeedle=true);
        list($edition, $version) = explode('-', $basename);
        return $version;
    }

    protected function _getReadableVersionString($edition, $version)
    {
        return $edition . ' ' . $version;
    }

    /**
     * connect to tag database
     *
     * @return void
     */
    protected function _connectTagDatabase()
    {
        $basedir = realpath(dirname(__FILE__) . '/../../');
        require_once $basedir . '/vendor/dg/dibi/dibi/dibi.php';
        if (false == dibi::isConnected()) {
            $databaseConfig = $this->_settings->database;
            if (0 == strlen($databaseConfig->password)) {
                unset($databaseConfig->password);
            }
            dibi::connect($databaseConfig);
            /*
             array(
                //'driver'   => 'sqlite3',
                //'database' => $basedir . '/plugins/MageCompatibility/var/tags.sqlite'
                'driver'   => 'mysql',
                'username' => 'root',
                'database' => 'judge'
            ));
         */
        }
    }

    protected function _containsNoLatestVersion($incompatibleVersions, $edition)
    {
        /* for now we assume, all versions start with "1." */
        $minCE = (int) str_replace('.', '', $this->_settings->min->ce);
        $minEE = (int) str_replace('.', '', $this->_settings->min->ee);
        foreach ($incompatibleVersions as $currentVersion) {
            $min = $minCE;
            if (strtolower(trim(substr($currentVersion, 0,2))) == 'ee') {
                $min = $minEE;
            }
            $current = (int) str_replace('.', '', substr($currentVersion, 3));
            if ($min < $current) {
                return false;
            }
        }

        return true;
    }
}
