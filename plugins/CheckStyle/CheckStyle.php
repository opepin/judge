<?php
namespace CheckStyle;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin\PluginAbstract as Plugin;

class CheckStyle extends Plugin
{
    protected $_results;
    protected $_uniqueIssues = array(
        'errors'    => array(),
        'warnings'  => array()
    );

    /**
     * Execution command
     * @var string
     */
    protected $_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';

    /**
     *
     * @param string $extensionPath the path to the extension to check
     */
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);
        $params = array(
            'ignore' => '*/jquery*',
            'standard' => $this->_settings->standardToUse
        );

        try {
            $csResults = $this->_executePhpCommand($this->_config, $params);
        } catch (\Zend_Exception $e) {
            return;
        }

        $csResults = $this->_getClearedResults($csResults);
        // more issues found than allowed -> log them
        if ($this->_settings->allowedIssues < sizeof($csResults)) {
            foreach ($csResults as $issue) {
                $this->_addToUniqueIssues($issue);
            }
            $this->_logUniqueIssues();
        }
    }

    /**
     *
     * removes header and so on from result
     *
     * @param array $results
     * @return array the
     */
    protected function _getClearedResults(array $results)
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
    protected function _addToUniqueIssues($issue)
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
            if (false === array_key_exists($issueClass, $this->_uniqueIssues)) {
                $this->_uniqueIssues[$issueClass] = array();
            }
            if (false === array_key_exists($issueMessage, $this->_uniqueIssues[$issueClass])) {
                $this->_uniqueIssues[$issueClass][$issueMessage] = 1;
            }
            if (true === array_key_exists($issueMessage, $this->_uniqueIssues[$issueClass])) {
                $this->_uniqueIssues[$issueClass][$issueMessage] ++;
            }
        }
    }

    /**
     * creates a summaritze of the unique issues
     */
    protected function _logUniqueIssues()
    {
        foreach ($this->_uniqueIssues as $issueType => $uniqueIssues) {
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
