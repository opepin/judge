<?php
namespace SourceCodeComplexity;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;
use Netresearch\Plugin as Plugin;

class SourceCodeComplexity extends Plugin implements JudgePlugin
{
    protected $config;
    protected $settings;
    protected $results;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->_pluginName   = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->_pluginName};
    }

    /**
     *
     * @param string $extensionPath the path to the extension to check
     */
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
        $this->executePHPDepend($extensionPath);
        $this->executePHPCpd($extensionPath);
        $this->executePHPMessDetector($extensionPath);
    }

    /**
     * checks the extension with phpMessDetector and returns the scoring
     *
     * @param string $extensionPath extension to check
     */
    protected function executePHPMessDetector($extensionPath)
    {
        $this->setExecCommand('vendor/phpmd/phpmd/src/bin/phpmd');
        $params = array($extensionPath, 'text', $this->settings->phpMessDetector->useRuleSets);
        try {
            $mdResults = $this->_executePhpCommand($this->config, $params);
        } catch (\Zend_Exception $e) {
            return $this->settings->unfinished;
        }

        if ($this->settings->phpMessDetector->allowedIssues < count($mdResults)) {
            foreach ($mdResults as $issue) {
                //prepare comment for db log
                $comment = null;
                $linenumber = null;
                $filename = null;
                $commentParts = explode(":", $issue, 2);
                if (count($commentParts) > 1) {
                    $filename = $commentParts[0];
                    $comment = $commentParts[1];
                }

                $commentParts = explode("\t", $comment, 2);
                if (count($commentParts) > 1)
                {
                    $linenumber = $commentParts[0];
                    $comment = $commentParts[1];
                }

                IssueHandler::addIssue(new Issue(
                        array(  "extension"     =>  $extensionPath,
                                "checkname"     =>  $this->_pluginName,
                                "type"          =>  'mess_detector',
                                "comment"       =>  $comment,
                                "linenumber"    =>  $linenumber,
                                "files"         =>  array($filename),
                                "failed"        =>  true)));
            }
        }
    }

    /**
     * checks the extensions complexity with phpDepend and returns the scoring
     *
     * @param string $extensionPath extension to check
     */
    protected function executePHPDepend($extensionPath)
    {
        $metricViolations = 0;
        $tempXml = str_replace('.xml', (string) $this->config->token . '.xml', $this->settings->phpDepend->tmpXmlFilename);
        $usedMetrics = $this->settings->phpDepend->useMetrics->toArray();
        $this->setExecCommand('vendor/pdepend/pdepend/src/bin/pdepend');
        $params = array(
            'summary-xml' => $tempXml,
        );
        try {
            $this->_executePhpCommand($this->config, $params);
        } catch (\Zend_Exception $e) {
            return $this->settings->unfinished;
        }
        $metrics = current(simplexml_load_file($tempXml));
        Logger::setResultValue($extensionPath, $this->_pluginName, 'metrics', $metrics);
        foreach ($metrics as $metricName => $metricValue) {
            if (in_array($metricName, $usedMetrics)
                && $this->settings->phpDepend->{$metricName} < $metricValue) {
                IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->_pluginName,
                                "type"      => $metricName,
                                "comment"   => $metricValue,
                                "failed"        =>  true)));  
                
                ++ $metricViolations;
            }
        }
        unlink($tempXml);
    }

    /**
     *  checks the extension with php copy and paste detector
     *
     * @param string $extensionPath extension to check
     */
    protected function executePHPCpd($extensionPath)
    {
        $minLines   = $this->settings->phpcpd->minLines;
        $minTokens  = $this->settings->phpcpd->minTokens;
        $verbose = null;
        $suffixes = '';
        $exclude  = array();
        $commonPath = false;

        $facade = new \File_Iterator_Facade;
        $files = $facade->getFilesAsArray(
            $extensionPath, $suffixes, array(), $exclude, $commonPath
        );

        $strategy = new \PHPCPD_Detector_Strategy_Default();
        
        $detector = new \PHPCPD_Detector($strategy, $verbose);

        $clones = @$detector->copyPasteDetection(
          $files, $minLines, $minTokens
        );
        $cpdPercentage = $clones->getPercentage();
        if ($this->settings->phpcpd->percentageGood < $cpdPercentage) {
            IssueHandler::addIssue(new Issue(
                    array(  "extension" =>  $extensionPath,
                            "checkname" => $this->_pluginName,
                            "type"      => 'duplicated_code',
                            "comment"   => $cpdPercentage,
                            "failed"        =>  true)));
            
        }
    }
}
