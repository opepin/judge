<?php
namespace CheckComments;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;
use Netresearch\Plugin as Plugin;

class CheckComments extends Plugin implements JudgePlugin
{
    protected $config;
    protected $settings;
    protected $ncloc = 0;
    protected $cloc = 0;



    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->_pluginName   = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->_pluginName};
        $this->_execCommand = 'vendor/pdepend/pdepend/src/bin/pdepend';
    }

    /**
     *
     * @param string $extensionPath the path to the extension to check
     * @return float the score for the extension for this test
     */
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
        $score = $this->settings->good;
        $lowerBoundary = $this->settings->lowerBoundary;
        $upperBoundary = $this->settings->upperBoundary;
        $clocToNclocRatio = $this->getClocToNclocRatio($extensionPath);
        
        $failed = ($clocToNclocRatio <= $lowerBoundary || $clocToNclocRatio >= $upperBoundary);
        $score = $failed ? $this->settings->bad : $this->settings->good;
        IssueHandler::addIssue(new Issue(
            array('extension' => $extensionPath ,
                  'checkname' => $this->_pluginName,
                  'type'      => 'cloc_to_ncloc',
                  'comment'   => $clocToNclocRatio,
                  'failed'    => $failed
            )
        ));


        $unfinishedCodeToNclocRatio = $this->getUnfinishedCodeToNclocRatio($extensionPath);
        $failed = $this->settings->allowedUnfinishedCodeToNclocRatio < $unfinishedCodeToNclocRatio;
        $score = $failed ? $this->settings->bad : $this->settings->good;
        IssueHandler::addIssue(new Issue(
            array('extension' => $extensionPath ,
                'checkname' => $this->_pluginName,
                'type'      => 'unfinished_code_to_ncloc',
                'comment'   => $unfinishedCodeToNclocRatio,
                'failed'    => $failed
            )
        ));

        Logger::setScore($extensionPath, $this->_pluginName, $score);
        return $score;
    }

    /**
     *
     * calculates the ratio between 'number logical lines of code' and 'number comment lines of code'
     *
     * @param string $extensionPath
     * @return float
     * @throws Exception if the ratio cannot be calculated
     */
    protected function getClocToNclocRatio($extensionPath)
    {
        $ncloc = 0;
        $cloc = 0;
        $metrics = $this->getMetrics($extensionPath);
        $this->ncloc = $metrics['ncloc'];
        $this->cloc = $metrics['cloc'];
        if ((!is_numeric($ncloc) || !is_numeric($cloc)) && $ncloc <= 0) {
            throw new Exception('Number of code lines is not numeric or 0? Please check extension path!');
        }
        return $this->cloc / $this->ncloc;
    }


    protected function getUnfinishedCodeToNclocRatio($extensionPath)
    {
        $unfinishedCode = 0;
        $precalculatedResults = Logger::getResults($extensionPath, 'CodeRuin');
        if (!is_null($precalculatedResults)
            && array_key_exists('resultValue', $precalculatedResults)) {
            foreach ($precalculatedResults['resultValue'] as $key => $value) {
                $unfinishedCode += $value;
            }
        }
        return $unfinishedCode / $this->ncloc;
    }


    /**
     * getting the metrics which are used for calculation
     * either the metrics came from a previous check or the metrics were calculated
     *
     * @param $extensionPath the extension path
     * @return array an array containning the *locs
     */
    protected function getMetrics($extensionPath)
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
            $tempXml = str_replace('.xml', (string) $this->config->token . '.xml', $this->settings->tmpXmlFilename);
            $params = array(
                'summary-xml' => $tempXml
            );

            try {
                $this->_executePhpCommand($this->config, $params);
            } catch (\Zend_Exception $e) {
                return $this->settings->unfinished;
            }
            $metrics = current(simplexml_load_file($tempXml));
            unlink($tempXml);
        }
        return $metrics;
    }
}
