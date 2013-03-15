<?php
namespace CodeRuin;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\PluginInterface as JudgePlugin;

class CodeRuin implements JudgePlugin
{
    protected $config;
    protected $extensionPath;
    protected $settings;
    protected $results;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->name   = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->name};
    }

    /**
     *
     * @param string $extensionPath the path to the extension to check
     * @return float the score for the extension for this test
     */
    public function execute($extensionPath)
    {
        $this->extensionPath = $extensionPath;
        $score = 0;

        $score += ($this->extensionContainsTokens($extensionPath, $this->settings->criticals, 'critical'))
            ? $this->settings->critical->bad
            : $this->settings->critical->good;

        $score += ($this->extensionContainsTokens($extensionPath, $this->settings->warnings, 'warning'))
            ? (int) $this->settings->warning->bad
            : $this->settings->warning->good;

        Logger::setScore($extensionPath, $this->name, $score);
        return $score;
    }

    protected function extensionContainsTokens($extensionPath, $tokens, $type)
    {
        $found = 0;
        foreach ($tokens as $token) {
            $filesWithThatToken = array();
            $command = 'grep -riEl "' . $token . '" ' . $extensionPath . '/app';
            exec($command, $filesWithThatToken, $return);
            $count = count($filesWithThatToken);
            if (0 < $count) {
                if(strcmp($type, 'critical') == 0) {
                    IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->name,
                                "type"      => 'critical',
                                "comment"   => $token,
                                "files"     => $filesWithThatToken,
                                "failed"    =>  true)));
                } else if(strcmp($type, 'warning') == 0) {
                    IssueHandler::addIssue(new Issue(
                        array(  "extension" =>  $extensionPath,
                                "checkname" => $this->name,
                                "type"      => 'warning',
                                "comment"   => $token,
                                "files"     => $filesWithThatToken,
                                "failed"    =>  true)));
                }
                
                
                $found += $count;
            }
        }
        return (0 < $found);
    }
}

