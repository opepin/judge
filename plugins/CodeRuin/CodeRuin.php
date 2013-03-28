<?php
namespace CodeRuin;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;
use Netresearch\Plugin as Plugin;

class CodeRuin extends Plugin implements JudgePlugin
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
     * @return float the score for the extension for this test
     */
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
        $score = 0;

        $score += ($this->extensionContainsTokens($extensionPath, $this->settings->criticals, 'critical'))
            ? $this->settings->critical->bad
            : $this->settings->critical->good;

        $score += ($this->extensionContainsTokens($extensionPath, $this->settings->warnings, 'warning'))
            ? (int) $this->settings->warning->bad
            : $this->settings->warning->good;

        Logger::setScore($extensionPath, $this->_pluginName, $score);
        return $score;
    }

    protected function extensionContainsTokens($extensionPath, $tokens, $type)
    {
        $found = 0;
        foreach ($tokens as $token) {
            $command = 'grep -riEl "' . $token . '" ' . $extensionPath . '/app';
            try {
                $filesWithThatToken = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return $this->settings->unfinished;
            }
            $count = count($filesWithThatToken);
            if ($count) {
                IssueHandler::addIssue(new Issue(
                    array('extension' =>  $extensionPath,
                          'checkname' => $this->_pluginName,
                          'type'      => $type,
                          'comment'   => $token,
                          'files'     => $filesWithThatToken,
                          'failed'    =>  true
                    )
                ));
                $found += $count;
            }
        }
        return (0 < $found);
    }
}

