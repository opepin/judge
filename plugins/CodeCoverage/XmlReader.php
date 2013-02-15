<?php
namespace CodeCoverage;

/**
 * Simple helper class to read selected information from XML files.
 * Keeps the plugin clean.
 *
 * @author Christoph Aßmann <christoph.assmann@netresearch.de>
 */
class XmlReader
{
    /**
     * Scan all config.xml files within the extension for the names of those
     * modules that contain unit tests.
     *
     * @param string $extensionPath The path to the extension to be evaluated
     * @return array The module names (NameSpace_ModuleName) to be checked
     */
    public static function getModuleNames($extensionPath)
    {
        $configFiles = array();
        $moduleNames = array();

        // detect the config files
        $command = 'grep -rl -m1 --include "config.xml" "\<phpunit>" ' . $extensionPath;
        exec($command, $configFiles);

        // read their phpunit modules
        foreach ($configFiles as $configFile) {
            $config = simplexml_load_file($configFile);
            $moduleNodes = $config->xpath('/config/phpunit/suite/modules/*');
            foreach ($moduleNodes as $moduleNode) {
                $moduleNames[] = $moduleNode->getName();
            }
        }

        return $moduleNames;
    }
}