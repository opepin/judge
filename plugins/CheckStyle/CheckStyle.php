<?php
namespace CheckStyle;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;
use Netresearch\Plugin as Plugin;

class CheckStyle extends Plugin implements JudgePlugin
{
    protected $config;
    protected $settings;
    protected $results;
    protected $uniqueIssues = array(
        'errors'    => array(),
        'warnings'  => array()
    );

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->_pluginName   = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->_pluginName};
        $this->_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';
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

        $params = array(
            'ignore' => '*/jquery*',
            'standard' => $this->settings->standardToUse
        );

        try {
            $csResults = $this->_executePhpCommand($this->config, $params);
        } catch (\Zend_Exception $e) {
            return $this->settings->unfinished;
        }

        $csResults = $this->getClearedResults($csResults);
        // more issues found than allowed -> log them
        if ($this->settings->allowedIssues < sizeof($csResults)) {
            $score = $this->settings->bad;
            foreach ($csResults as $issue) {
                $this->addToUniqueIssues($issue);
            }
            $this->logUniqueIssues();
        }
        Logger::setScore($extensionPath, $this->_pluginName, $score);
        return $score;
    }

    /**
     *
     * removes header and so on from result
     *
     * @param array $results
     * @return array the
     */
    protected function getClearedResults(array $results)
    {
        $newResults = array();
        foreach ($results as $resultLine) {
            if (false !== strpos($resultLine, '|') &&
                (false !== strpos(strtolower($resultLine), 'error') ||
                 false !== strpos(strtolower($resultLine), 'warning'))) {
                $newResults[] = $resultLine;
            }
        }
        $results = $newResults;
        return $results;
    }

    /**
     * counts the unique issues
     *
     * @param string $issue
     */
    protected function addToUniqueIssues($issue)
    {
        $issueData = explode('|', $issue);
        if (3 == count($issueData)) {
            $issueClass     = trim($issueData[1]);
            $issueMessage = trim($issueData[2]);
            if (false !== strpos($issueData[2], ';')) {
                $issueMessage = substr(
                    $issueMessage,
                    0,
                    strpos($issueMessage, ';')
                );
            }
            if (false === array_key_exists($issueClass, $this->uniqueIssues)) {
                $this->uniqueIssues[$issueClass] = array();
            }
            if (false === array_key_exists($issueMessage, $this->uniqueIssues[$issueClass])) {
                $this->uniqueIssues[$issueClass][$issueMessage] = 1;
            }
            if (true === array_key_exists($issueMessage, $this->uniqueIssues[$issueClass])) {
                $this->uniqueIssues[$issueClass][$issueMessage] ++;
            }
        }
    }

    /**
     * creates a summaritze of the unique issues
     */
    protected function logUniqueIssues()
    {
        foreach ($this->uniqueIssues as $issueType => $uniqueIssues) {
            foreach ($uniqueIssues as $message => $count) {
                IssueHandler::addIssue(new Issue(
                        array(
                            'extension' =>  $this->_extensionPath,
                            'checkname' =>  $this->_pluginName,
                            'type'      =>  strtolower($issueType),
                            'comment'   =>  $message . ' (' . $count . ' times).',
                            'failed'    =>  true
                        )
                ));
            }
        }
    }
}
