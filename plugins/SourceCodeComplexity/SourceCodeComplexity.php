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
     * @return float the sum of scores of all tests
     */
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
        $score = 0;
        if ($this->settings->phpDepend->enabled) {
            $score = $score + $this->executePHPDepend($extensionPath);
        }
        if ($this->settings->phpcpd->enabled) {
            $score = $score + $this->executePHPCpd($extensionPath);
        }
        if ($this->settings->phpMessDetector->enabled) {
            $score = $score + $this->executePHPMessDetector($extensionPath);
        }
        Logger::setScore($extensionPath, $this->_pluginName, $score);
        return $score;
    }

    /**
     * checks the extension with phpMessDetector and returns the scoring
     *
     * @param string $extensionPath extension to check
     * @return float the scoring for the extension after php mess detector test
     */
    protected function executePHPMessDetector($extensionPath)
    {
        $this->setExecCommand('vendor/phpmd/phpmd/src/bin/phpmd');
        $score = $this->settings->phpMessDetector->good;
        $params = array($extensionPath, 'text', $this->settings->phpMessDetector->useRuleSets);
        try {
            $mdResults = $this->_executePhpCommand($this->config, $params);
        } catch (\Zend_Exception $e) {
            return $this->settings->unfinished;
        }

        if ($this->settings->phpMessDetector->allowedIssues < count($mdResults)) {
            $score = $this->settings->phpMessDetector->bad;
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
        return $score;
    }

    /**
     * checks the extensions complexity with phpDepend and returns the scoring
     *
     * @param string $extensionPath extension to check
     * @return float the scoring for the extension after php depend test
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
        $score = $this->settings->phpDepend->metricViolations->good;
        if ($this->settings->phpDepend->metricViolations->allowedMetricViolations < $metricViolations) {
            $score = $score + $this->settings->phpDepend->metricViolations->bad;
        }
        unlink($tempXml);
        return $score;
    }

    /**
     *  checks the extension with php copy and paste detector
     *
     * @param string $extensionPath extension to check
     * @return float the scoring for the extension after phpcpd test
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
            
            return $this->settings->phpcpd->bad;
        }
        return $this->settings->phpcpd->good;
    }
}
