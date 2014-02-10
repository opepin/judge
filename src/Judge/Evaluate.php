<?php
namespace Judge;

use Netresearch\Logger;
use Netresearch\XMLReader;
use Netresearch\Config;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

require_once __DIR__ . '/../../vendor/mthaml/mthaml/lib/MtHaml/Autoloader.php';
require 'vendor/nikic/php-parser/lib/bootstrap.php';

use MtHaml\Environment as HamlGenerator;
use MtHaml\Autoloader as HamlLoader;

use \Exception as Exception;

/**
 * Initiate evaluating a Magento extension
 *
 * @package    Judge
 * @subpackage Judge
 * @author     Thomas Birke <thomas.birke@netresearch.de>
 */
class Evaluate extends Command
{
    /**
     * Judge's configuration
     *
     * @var \Netresearch\Config
     */
    protected $config;

    /**
     * Judge base dir path
     *
     * @var string
     */
    protected $basePath;

    protected function configure()
    {
        $this->setName('evaluate');
        $this->setDescription('Detect Core Hacks');
        $this->addArgument('extensions', InputArgument::REQUIRED, 'path to the extensions to judge (separate by ",")');
        $this->addOption('config',  'c', InputOption::VALUE_OPTIONAL, 'provide a configuration file', 'ini/sample.judge.ini');
        $this->addOption('user-token',  't', InputOption::VALUE_OPTIONAL, 'unique token for user session definition');
        $this->addOption('vendor',  'd', InputOption::VALUE_OPTIONAL, 'provide the vendor of the extension');
        $this->addOption('extension',  'e', InputOption::VALUE_OPTIONAL, 'provide the name of the extension');
        $this->addOption('ext_version',  's', InputOption::VALUE_OPTIONAL, 'provide the extension version');
        $this->addOption('ext_identifier',  'i', InputOption::VALUE_OPTIONAL, 'unique identifier of the extension');
        $this->addOption('php-option', 'o', InputOption::VALUE_OPTIONAL, 'php options for child processes');
    }

    /**
     * @see vendor/symfony/src/Symfony/Component/Console/Command/Symfony\Component\Console\Command.Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->config = new Config($input->getOption('config'), null, array('allowModifications' => true));
        $this->config->setOutput($output);
        $this->config->setCommand($this);
        $this->config->setBaseDirPath($this->getBasePath());
        if ($input->getOption('no-interaction')) {
            $this->config->disableInteractivity();
        }
        Logger::setOutputInterface($output);
        if ($input->getOption('quiet')) {
            Logger::setVerbosity(Logger::VERBOSITY_NONE);
        }
        if ($input->getOption('verbose')) {
            Logger::setVerbosity(Logger::VERBOSITY_MAX);
        }

        if ($input->getOption('user-token')) {
            Logger::setToken($input->getOption('user-token'));
            $this->config->token = $input->getOption('user-token');
        }

        if ($input->getOption('php-option')) {
            $this->config->phpOptions = explode(',', $input->getOption('php-option'));
            foreach ($this->config->phpOptions as $options) {
                list($option, $value) = explode('=', $options);
                ini_set($option, $value);

            }
        }
        
        
        $results = array();

        foreach (explode(',', $input->getArgument('extensions')) as $extensionPath) {
            $extensionPath = realpath($extensionPath);
            
            //get vendor, name and version of extension
            $this->getExtensionAttributes($input, $extensionPath);
        
            $plugins = $this->config->getPlugins();
            foreach ($plugins as $name => $settings) {
                $results[$extensionPath] = 0;
                // check if plugin was defined in ini, but disabled
                if ('0' === $settings->checkEnabled) {
                    Logger::log('Skipping plugin "%s"', array($name));
                    continue;
                }
                
                // set path to plugin by convention
                $path = $this->getBasePath() . 'plugins' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR;

                // load script file
                $file =  $path . $name . '.php';
                if (!file_exists($file)) {
                    Logger::error('Could not find plugin "%s"', array($name), $stop=false);
                    Logger::log('Expected it at path "%s"', array($path));
                    continue;
                }

                // load default judge config for plugin execution
                $pluginConfig = $this->config;

                $customIni = $settings->ini;
                if ((null !== $customIni) && file_exists($customIni)) {
                    unset($settings->ini);
                    // add custom config settings, if given
                    $pluginConfig = new Config($customIni, null, array('allowModifications' => true));
                    $pluginConfig->merge($this->config);
                }

                $class = "$name\\$name";
                $plugin = new $class($pluginConfig);
                Logger::addCheck($extensionPath, $name);
                Logger::registerCheck($extensionPath, $name);
                $plugin->execute($extensionPath);
            }
            
            //swap logger output
            $logger = $this->config->getLogger();
            
            foreach ($logger as $name => $settings) {
                if( $name == 'output' && ( $settings === 'webservice' | $settings === 'console') )
                {
                    Logger::setLoggerOutput($settings);
                }
                if( $name == 'user')
                {
                    Logger::setUser($settings);
                }
                if( $name == 'password')
                {
                    Logger::setPassword($settings);
                }
                if( $name == 'host')
                {
                    Logger::setHost($settings);
                }
            }
            
            Logger::printResults($extensionPath);
            $this->generateResultHtml($extensionPath);
        }
    }

    
    protected function getExtensionAttributes($input, $extensionPath)
    {
        //read from config if all values are empty
        if( is_null($input->getOption('vendor')) || 
                is_null($input->getOption('extension')) || 
                        is_null($input->getOption('ext_version'))) {
            XMLReader::readConfig($extensionPath);
        }
        
        if ($input->getOption('vendor')) {
            Logger::setExtVendor($input->getOption('vendor'));
        } else {
            //read vendor from config
            Logger::setExtVendor(XMLReader::getVendor());
        }
        if ($input->getOption('extension')) {
            Logger::setExtName($input->getOption('extension'));
        } else {
            //read extension name from config
            Logger::setExtName(XMLReader::getExtensionName());
        }
        if ($input->getOption('ext_version')) {
            Logger::setExtVersion($input->getOption('ext_version'));
        } else {
            //read extension version from config
            Logger::setExtVersion(XMLReader::getVersion());
        }
        if ($input->getOption('ext_identifier')) {
            Logger::setExtIdentifier($input->getOption('ext_identifier'));
        } else {
            //read extension identifier from config
            Logger::setExtIdentifier(XMLReader::getExtensionIdentifier());
        }
    }

    /**
     * Define base path
     *
     * @return string
     */
    protected function getBasePath()
    {
        if (empty($this->basePath)) {
            $this->basePath = realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR
                . '..' . DIRECTORY_SEPARATOR
                . '..' . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
        return $this->basePath;
    }

    protected function generateResultHtml($extension)
    {
        HamlLoader::register();
        $haml = new HamlGenerator('php', array('enable_escaper' => false));
        $template   = 'Resources/views/result.haml';
        $targetFile = 'tmp/result.php';
        $compiled   = $haml->compileString(file_get_contents($template), $template);
        file_put_contents($targetFile, $compiled);

//        $results = $this->convertResultCommentsToHtml(
//            Logger::getResultArray($extension)
//        );
//        $results = $this->convertResultCommentsToHtml(Logger::getIssueResults($extension));
        
        $passedChecks = Logger::getPassedChecksOfIssueHandler($extension);
        $failedChecks = Logger::getFailedChecksOfIssueHandler($extension);
//        $failedChecks = $results['failedChecks'];
//        $score        = $results['score'];
        
        ob_start();
        include($targetFile);
        $result = ob_get_contents();
        ob_end_clean();
        $p = array_reverse (explode("/", $extension));	
        $targetHtml = 'tmp/' . $p[1] . '_' . $p[0] . '_result.html';
        file_put_contents($targetHtml, $result);
    }

    protected function convertResultCommentsToHtml($results) {
        foreach ($results as $type=>$checks) {
            if (is_array($checks)) {
                foreach ($checks as $check=>$checkResult) {
                    foreach ($checkResult['comments'] as $key=>$comment) {
                        $results[$type][$check]['comments'][$key] = strtr(
                            $comment,
                            array(
                                '<comment>'  => '<span class="warning">',
                                '</comment>' => '</span>',
                                '<info>'     => '<span class="success">',
                                '</info>'    => '</span>',
                                '<error>'    => '<span class="error">',
                                '</error>'   => '</span>',
                            )
                        );
                    }
                }
            }
        }
        return $results;
    }
}
