<?php
namespace SourceCodeComplexity;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin\Plugin as Plugin;

class SourceCodeComplexity extends Plugin
{
    protected $_results;

    /**
     * Execute the SourceCodeComplexity plugin
     */
    protected function _execute()
    {
        $this->_executePHPDepend($this->_extensionPath);
        $this->_executePHPCpd($this->_extensionPath);
        $this->_executePHPMessDetector($this->_extensionPath);
    }

    /**
     * checks the extension with phpMessDetector and returns the scoring
     *
     * @param string $extensionPath extension to check
     */
    protected function _executePHPMessDetector($extensionPath)
    {
        $this->setExecCommand('vendor/phpmd/phpmd/src/bin/phpmd');
        $options = array($extensionPath, 'text', $this->_settings->phpMessDetector->useRuleSets);
        try {
            $mdResults = $this->_executePhpCommand($options);
        } catch (\Zend_Exception $e) {
            return;
        }

        if ($this->_settings->phpMessDetector->allowedIssues < count($mdResults)) {
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
    protected function _executePHPDepend($extensionPath)
    {
        $metricViolations = 0;
        $tempXml = str_replace('.xml', (string) $this->_config->token . '.xml', $this->_settings->phpDepend->tmpXmlFilename);
        $usedMetrics = $this->_settings->phpDepend->useMetrics->toArray();
        $this->setExecCommand('vendor/pdepend/pdepend/src/bin/pdepend');
        $options = array(
            'summary-xml' => $tempXml,
        );
        try {
            $this->_executePhpCommand($options);
        } catch (\Zend_Exception $e) {
            return;
        }
        $metrics = current(simplexml_load_file($tempXml));
        Logger::setResultValue($extensionPath, $this->_pluginName, 'metrics', $metrics);
        foreach ($metrics as $metricName => $metricValue) {
            if (in_array($metricName, $usedMetrics)
                && $this->_settings->phpDepend->{$metricName} < $metricValue) {
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
    protected function _executePHPCpd($extensionPath)
    {
        $minLines   = $this->_settings->phpcpd->minLines;
        $minTokens  = $this->_settings->phpcpd->minTokens;
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
        if ($this->_settings->phpcpd->percentageGood < $cpdPercentage) {
            IssueHandler::addIssue(new Issue(
                    array(  "extension" =>  $extensionPath,
                            "checkname" => $this->_pluginName,
                            "type"      => 'duplicated_code',
                            "comment"   => $cpdPercentage,
                            "failed"        =>  true)));
            
        }
    }
}
