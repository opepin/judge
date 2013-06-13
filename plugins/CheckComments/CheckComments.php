<?php
namespace CheckComments;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin\Plugin as Plugin;

class CheckComments extends Plugin
{
    protected $_ncloc = 0;
    protected $_cloc = 0;

    /**
     * Execution command
     * @var string
     */
    protected $_execCommand = 'vendor/pdepend/pdepend/src/bin/pdepend';

    /**
     * Execute the CheckComments plugin
     */
    protected  function _execute()
    {
        $lowerBoundary = $this->_settings->lowerBoundary;
        $upperBoundary = $this->_settings->upperBoundary;
        $clocToNclocRatio = $this->_getClocToNclocRatio($this->_extensionPath);
        
        $failed = ($clocToNclocRatio <= $lowerBoundary || $clocToNclocRatio >= $upperBoundary);
        IssueHandler::addIssue(new Issue(
            array('extension' => $this->_extensionPath ,
                  'checkname' => $this->_pluginName,
                  'type'      => 'cloc_to_ncloc',
                  'comment'   => $clocToNclocRatio,
                  'failed'    => $failed
            )
        ));


        $unfinishedCodeToNclocRatio = $this->_getUnfinishedCodeToNclocRatio($this->_extensionPath);
        $failed = $this->_settings->allowedUnfinishedCodeToNclocRatio < $unfinishedCodeToNclocRatio;
        IssueHandler::addIssue(new Issue(
            array('extension' => $this->_extensionPath ,
                'checkname' => $this->_pluginName,
                'type'      => 'unfinished_code_to_ncloc',
                'comment'   => $unfinishedCodeToNclocRatio,
                'failed'    => $failed
            )
        ));

    }

    /**
     *
     * calculates the ratio between 'number logical lines of code' and 'number comment lines of code'
     *
     * @param string $extensionPath
     * @return float
     * @throws \Exception if the ratio cannot be calculated
     */
    protected function _getClocToNclocRatio($extensionPath)
    {
        $ncloc = 0;
        $cloc = 0;
        $metrics = $this->_getMetrics($extensionPath);
        $this->_ncloc = $metrics['ncloc'];
        $this->_cloc = $metrics['cloc'];
        if ((!is_numeric($ncloc) || !is_numeric($cloc)) && $ncloc <= 0) {
            throw new \Exception('Number of code lines is not numeric or 0? Please check extension path!');
        }
        return $this->_cloc / $this->_ncloc;
    }


    protected function _getUnfinishedCodeToNclocRatio($extensionPath)
    {
        $unfinishedCode = 0;
        $precalculatedResults = Logger::getResults($extensionPath, 'CodeRuin');
        if (!is_null($precalculatedResults)
            && array_key_exists('resultValue', $precalculatedResults)) {
            foreach ($precalculatedResults['resultValue'] as $key => $value) {
                $unfinishedCode += $value;
            }
        }
        return $unfinishedCode / $this->_ncloc;
    }


    /**
     * getting the metrics which are used for calculation
     * either the metrics came from a previous check or the metrics were calculated
     *
     * @param string $extensionPath the extension path
     * @return array an array containning the *locs
     */
    protected function _getMetrics($extensionPath)
    {
        $metrics = array();
        $precalculatedResults = Logger::getResults($extensionPath, 'SourceCodeComplexity');
        if (!is_null($precalculatedResults)
            && array_key_exists('resultValue', $precalculatedResults)
            && array_key_exists('metrics', $precalculatedResults['resultValue'])
            && array_key_exists('ncloc', $precalculatedResults['resultValue']['metrics'])
            && array_key_exists('cloc', $precalculatedResults['resultValue']['metrics'])
        ) {
            $metrics = $precalculatedResults['resultValue']['metrics'];
        }
        if (0 == count($metrics)) {
            $tempXml = str_replace('.xml', (string) $this->_config->token . '.xml', $this->_settings->tmpXmlFilename);
            $options = array(
                'summary-xml' => $tempXml
            );

            try {
                $this->_executePhpCommand($options);
            } catch (\Zend_Exception $e) {
                return array();
            }
            $metrics = current(simplexml_load_file($tempXml));
            unlink($tempXml);
        }
        return $metrics;
    }
}
