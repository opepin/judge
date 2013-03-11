<?php
namespace CodeCoverage;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;
use \Zend_Exception as Exception;

class CodeCoverage implements JudgePlugin
{
    /**
     * The global Judge configuration
     * @var \Zend_Config_Ini
     */
    protected $config;

    /**
     * The local plugin configuration
     * @var \Zend_Config_Ini
     */
    protected $settings;

    /**
     * The filesystem path to the Magento installation
     * @var string
     */
    protected $magentoTarget;

    /**
     * The names of the extensions to be evaluated as given given in their
     * config.xml file, <phpunit> section.
     * @link http://www.ecomdev.org/2011/02/01/phpunit-and-magento-yes-you-can.html
     * @var array
     */
    protected $moduleNames;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->name   = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->name};
    }

    /**
     * Execute the CodeCoverage plugin (entry point)
     *
     * @param string $extensionPath The path to the extension to check
     * @return float The test result (score)
     * @throws \Zend_Exception
     */
    public function execute($extensionPath)
    {
        // xdebug is mandatory for code coverage test
        if (!extension_loaded('xdebug')) {
            throw new Exception("The Xdebug extension is not loaded.");
        }

        // scan for phpunit configuration
        $this->moduleNames = XmlReader::getModuleNames($extensionPath);
        if (empty($this->moduleNames)) {
            $score = $this->settings->bad;
            return $score;
        }

        $this->setupUnitTestEnvironment($extensionPath);

        $score = $this->evaluateTestCoverage($extensionPath);
        Logger::setScore($extensionPath, $this->name, $score);
        return $score;
    }

    /**
     *
     * calculates test coverage and find classes which are not covered
     * by any test
     *
     * @param string $extensionPath
     * @return float the score for test coverage
     */
    protected function evaluateTestCoverage($extensionPath)
    {
        $score = $this->settings->good;
        $executable = 'vendor/bin/phpunit';
        $phpUnitCoverageFile = 'tmp/codecoverage.xml';

        $phpUnitSwitches = array(
            sprintf("--coverage-clover %s", $phpUnitCoverageFile),
            sprintf("--filter %s", implode('|', $this->moduleNames)),
            sprintf("--include-path %s", $this->magentoTarget)
        );
        if (isset($this->settings->phpUnitSwitches)) {
            $phpUnitSwitches = array_merge(
                $phpUnitSwitches,
                $this->settings->phpUnitSwitches->toArray()
            );
        }
        $switches = implode(' ', $phpUnitSwitches);
        $testFile = $this->magentoTarget . '/UnitTests.php';
        exec("$executable $switches $testFile");




        $pdependSummaryFile = 'summary.xml';
        $execString = sprintf('vendor/pdepend/pdepend/src/bin/pdepend --summary-xml="%s" "%s"', $pdependSummaryFile, $extensionPath);
        exec($execString);
        $phpUnitXpaths = array();
        foreach ($this->moduleNames as $modulePrefixString) {
            $phpUnitXpaths[] = "//class[starts-with(@name, '" . $modulePrefixString . "')]/../metrics";
        }
        $codeCoverages = $this->evaluateCodeCoverage($phpUnitCoverageFile, $phpUnitXpaths);
        $codeCoverageSettings = $this->settings->phpUnitCodeCoverages->toArray();
        foreach (array_keys($codeCoverageSettings) as $codeCoverageType) {
            if (array_key_exists($codeCoverageType, $codeCoverages)) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->name,
                                "type"      => $codeCoverageType,
                                "comment"   => $codeCoverages[$codeCoverageType])));
            if ($codeCoverages[$codeCoverageType] < $codeCoverageSettings[$codeCoverageType]) {
                    $score = $this->settings->bad;
                }
            }
        }

        // compare phpunit test results with pdepend
        $phpUnitXpaths = array();
        $pdependXpaths = array();
        foreach ($this->moduleNames as $modulePrefixString) {
            $phpUnitXpaths[] = "//class[starts-with(@name, '" . $modulePrefixString . "')]";
            $pdependXpaths[] = "//class[starts-with(@name, '" . $modulePrefixString . "')  and not(starts-with(@name, '" . $modulePrefixString . '_Test' . "'))]";
        }
        $phpUnitClasses = $this->getClasses($phpUnitCoverageFile, $phpUnitXpaths);
        $pdependClasses = $this->getClasses($pdependSummaryFile, $pdependXpaths);
        $notCoveredClasses = array_diff($pdependClasses, $phpUnitClasses);

        if (0 < sizeof($notCoveredClasses)) {
            if ($this->settings->allowedNotCoveredClasses < sizeof($notCoveredClasses)) {
                $score = $this->settings->bad;
            }
            foreach ($notCoveredClasses as $notCoveredClass) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->name,
                                "type"      => 'none',
                                "comment"   => '<comment>Following class is not covered by any test: ' . $notCoveredClass . ' </comment>',
                                "failed"    =>  false)));
            }
        }
        unlink($pdependSummaryFile);
        unlink($phpUnitCoverageFile);

        return $score;
    }

    /**
     * gets the classes which are contained in a xml report file
     *
     * @param string $pathToXmlFile - the path to the report file
     * @param string $xpathExpression - the xpath for retrieving the class names
     * @return type
     */
    protected function getClasses($pathToXmlFile, $xpathExpressions)
    {
        $classes = array();
        foreach ($xpathExpressions as $xpathExpression) {
            $classNodes = $this->getNodes($pathToXmlFile, $xpathExpression);
            if (!is_null($classNodes)) {
                foreach ($classNodes as $classNode) {
                    // collect class names for determinig those which weren't covered by a test
                    if (!in_array($classNode['name'], $classes)) {
                        $classes[] = current($classNode[0]['name']);
                    }
                }
            }
        }
        return $classes;
    }


    /**
     *
     * evaluates the code coverage by PHPUnit tests
     *
     * @param string $pathToXmlReport - the xml containing the results for the classes
     * @param string $xpathExpression - the xpath for retrievibng the results for the classes
     * @return array - the array containing the code coverage results
     */
    protected function evaluateCodeCoverage($pathToXmlReport, $xpathExpressions)
    {
        $valuesForClasses = array(
            'coveredmethods'        => 0,
            'methods'               => 0,
            'coveredstatements'     => 0,
            'statements'            => 0,
            'coveredconditionals'   => 0,
            'conditionals'          => 0,
            'coveredelements'       => 0,
            'elements'              => 0
        );
        $codeCoverage = array(
            'methodCoverage'        => 0,
            'statementCoverage'     => 0,
            'conditionalsCoverage'  => 0,
            'elementsCoverage'      => 0
        );
        foreach ($xpathExpressions as $xpathExpression) {
            $classNodes = $this->getNodes($pathToXmlReport, $xpathExpression);
            if (!is_null($classNodes)) {
                foreach ($classNodes as $classNode) {
                    foreach (array_keys($valuesForClasses) as $key) {
                        $valuesForClasses[$key] += $this->getValueForNodeAttr($classNode, $key);
                    }
                }
            }
            $codeCoverage['methodCoverage']         += $this->getCoverageRatio($valuesForClasses['coveredmethods'], $valuesForClasses['methods']);
            $codeCoverage['statementCoverage']      += $this->getCoverageRatio($valuesForClasses['coveredstatements'], $valuesForClasses['statements']);
            $codeCoverage['conditionalsCoverage']   += $this->getCoverageRatio($valuesForClasses['coveredconditionals'], $valuesForClasses['conditionals']);
            $codeCoverage['elementsCoverage']       += $this->getCoverageRatio($valuesForClasses['coveredelements'], $valuesForClasses['elements']);
        }
        return $codeCoverage;
    }

    /**
     * retrieves the nodes of an xml document for given xpath
     *
     * @param string $pathToXmlReport - the path to xml document
     * @param string $xpathExpression - the xpath for retrieving the nodes
     * @return array - the nodes
     */
    protected function getNodes($pathToXmlReport, $xpathExpression)
    {
        $xmlElement = simplexml_load_file($pathToXmlReport);
        $classNodes = null;
        if ($xmlElement instanceof \SimpleXMLElement) {
            $classNodes = $xmlElement->xpath($xpathExpression);
        }
        return $classNodes;
    }

    /**
     * gets the attribute value from a given node by the attributes name
     * @param \SimpleXMLElement $node
     * @param string $attrName
     * @return mixed - the value
     */
    protected function getValueForNodeAttr(\SimpleXMLElement $node, $attrName)
    {
        $value = 0;
        if (!is_null($node[$attrName])) {
            $value = current($node[$attrName]);
        }
        return $value;
    }

    /**
     *
     * calculates the ratio between covered code and total amount of code
     *
     * @param float $covered
     * @param float $total
     * @return float -the ratio between covered and total
     */
    protected function getCoverageRatio($covered, $total)
    {
        $ratio = 0;
        if (is_numeric($covered) && is_numeric($total) && $total > 0) {
            $ratio = $covered / $total;
        }
        return $ratio;
    }


    /**
     * Check if all prerequisites for running unit tests are fullfilled or can
     * be fulfilled by using Jumpstorm.
     *
     * @param string $extensionPath Path to the extension to be evaluated
     * @throws \Zend_Exception
     */
    protected function setupUnitTestEnvironment($extensionPath)
    {
        if (!$this->settings->jumpstormIniFile) {
            throw new Exception("Required information missing in ini file: plugins.CodeCoverage.jumpstormIniFile");
        }

        $jumpstormConfig = new \Zend_Config_Ini(
            $this->settings->jumpstormIniFile,
            null,
            array('allowModifications' => true)
        );
        // check if required ini section is given
        if (!$jumpstormConfig->common) {
            throw new Exception("Required information missing in jumpstorm ini file: [common]");
        }

        if ($this->config->token) {
            $jumpstormConfig->common->magento->target = $jumpstormConfig->common->magento->target . '_' . $this->config->token;
            $jumpstormConfig->common->db->name = $jumpstormConfig->common->db->name . '_' . $this->config->token;
        }

        $this->magentoTarget = rtrim($jumpstormConfig->common->magento->target, DIRECTORY_SEPARATOR);

        // import test environment configuration from console: [extensions] section
        $jumpstormConfig->extensions = array(
            'extension' => array('source' => $extensionPath)
        );

        // force (re)installation using jumpstorm
        if ($this->settings->useJumpstorm) {
            $requiredSections = array('magento', 'unittesting');
            foreach ($requiredSections as $section) {
                if (!$jumpstormConfig->{$section}) {
                    throw new Exception("Required information missing in jumpstorm ini file: [$section]");
                }
            }

            $iniFile = 'tmp/jumpstorm.ini';
            $writer = new \Zend_Config_Writer_Ini();
            $writer->write($iniFile, $jumpstormConfig);

            $executable = 'vendor/netresearch/jumpstorm/jumpstorm';
            $params = '';
            $exec = 'exec';
            if (Logger::VERBOSITY_MAX == Logger::getVerbosity()) {
                $params .= ' -v';
            }
            $command = sprintf('%s magento -c %s %s', $executable, $iniFile, $params)
                . ' && ' . sprintf('%s unittesting -c %s %s', $executable, $iniFile, $params)
                . ' && ' . sprintf('%s extensions -c %s %s', $executable, $iniFile, $params);

            Logger::notice('Setting up Magento environment via Jumpstorm');
            if (Logger::VERBOSITY_MAX == Logger::getVerbosity()) {
                $error = passthru($command);
            } else {
                exec($command, $output, $error);
                if ($error) {
                    $origVerbosity = Logger::getVerbosity();
                    Logger::setVerbosity(Logger::VERBOSITY_MAX);
                    Logger::log(implode(PHP_EOL, $output));
                    Logger::setVerbosity($origVerbosity);
                }
            }
            if ($error) {
                Logger::error('Magento installation failed');
            }
            unlink($iniFile);
        }

//        if (!is_file($this->magentoTarget . '/UnitTests.php')) {
//            throw new Exception(sprintf(
//                "No UnitTests.php found in Magento root directory (%s)",
//                $this->magentoTarget
//            ));
//        }
    }
}
