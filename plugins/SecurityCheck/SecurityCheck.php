<?php
namespace SecurityCheck;

use Netresearch\Config;
use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue;
use Netresearch\PluginInterface as JudgePlugin;

class SecurityCheck implements JudgePlugin
{
    protected $config;
    protected $extensionPath;
    protected $settings;
    protected $results;

    /**
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->name   = current(explode('\\', __CLASS__));
        $this->settings = $this->config->plugins->{$this->name};
    }

    /**
     *
     * @param type $extensionPath
     * @return float the score for this test
     */
    public function execute($extensionPath)
    {
        $this->extensionPath = $extensionPath;
        $settings = $this->config->plugins->{$this->name};
        $score = $settings->good;
        if ($settings->allowedRequestParams < $this->checkForRequestParams($extensionPath)) {
            $score = $settings->bad;
        }
        if ($settings->allowedMissingEscaping < $this->checkForEscaping($extensionPath)) {
            $score = $settings->bad;
        }
        if ($settings->allowedSQLQueries < $this->checkForSQLQueries($extensionPath)) {
            $score = $settings->bad;
        }
        if ($score == $settings->good) {
            Logger::success('No direct usage of request params, sql queries or unescaped output ' . $extensionPath);
        }
        Logger::setScore($extensionPath, $this->name, $score);
        return $score;
    }


    /**
     *
     * @param string $extensionPath
     * @return int number of files containing direct usage of request params
     */
    protected function checkForRequestParams($extensionPath)
    {
        $foundTokens = 0;
        foreach ($this->settings->requestParamsPattern as $requestPattern) {
            $filesWithThatToken = array();
            $command = 'grep -riEl "' . $requestPattern . '" ' . $extensionPath . '/app';
            exec($command, $filesWithThatToken, $return);
            if (0 < count($filesWithThatToken)) {
                Logger::addComment($extensionPath, $this->name, sprintf(
                    '<comment>Found an indicator of using direct request params:</comment>"%s" at %s',
                    $requestPattern,
                    implode(';' . PHP_EOL, $filesWithThatToken)
                ));
                
                $issue = new Issue();
                IssueHandler::addIssue($issue->setCheckName($this->name)
                        ->setType('params')
                        ->setComment($requestPattern));
                
//                $this->issueHandler->addFilesForIssue($filesWithThatToken);
//                $this->issueHandler->addIssue($this->name, 'params', 
//                        $requestPattern);
//                $this->issueHandler->save();
                
                $foundTokens = $foundTokens + count($filesWithThatToken);
            }
            Logger::setResultValue($extensionPath, $this->name, $requestPattern, count($filesWithThatToken));
        }
        return $foundTokens;
    }


    /**
     *
     * @param string $extensionPath
     * @return int number of files containing unescaped output
     */
    protected function checkForEscaping($extensionPath)
    {
        $foundTokens = 0;
        foreach ($this->settings->unescapedOutputPattern as $unescapedOutputPattern) {
            $filesWithThatToken = array();
            $command = 'grep -riEl "' . $unescapedOutputPattern . '" ' . $extensionPath . '/app';
            exec($command, $filesWithThatToken, $return);
            if (0 < count($filesWithThatToken)) {
                Logger::addComment($extensionPath, $this->name, sprintf(
                    '<comment>Found an indicator of not escaping output:</comment>"%s" at %s',
                    $unescapedOutputPattern,
                    implode(';' . PHP_EOL, $filesWithThatToken)
                ));
                
                $issue = new Issue();
                IssueHandler::addIssue($issue->setCheckName($this->name)
                        ->setType('escape')
                        ->setComment($unescapedOutputPattern));
                
//                $this->issueHandler->addFilesForIssue($filesWithThatToken);
//                $this->issueHandler->addIssue($this->name, 'escape', 
//                        $unescapedOutputPattern);
//                $this->issueHandler->save();
                
                $foundTokens = $foundTokens + count($filesWithThatToken);
            }
            Logger::setResultValue($extensionPath, $this->name, $unescapedOutputPattern, count($filesWithThatToken));
        }
        return $foundTokens;
    }

    /**
     *
     * @param type $extensionPath
     * @return int number of files containing direct usage of sql queries
     */
    protected function checkForSQLQueries($extensionPath)
    {
        $foundTokens = 0;
        foreach ($this->settings->sqlQueryPattern as $sqlQueryPattern) {
            $filesWithThatToken = array();
            $command = 'grep -riEl "' . $sqlQueryPattern . '" ' . $extensionPath . '/app';
            exec($command, $filesWithThatToken, $return);
            if (0 < count($filesWithThatToken)) {
                Logger::addComment($extensionPath, $this->name, sprintf(
                    '<comment>Found an indicator of using direct sql queries:</comment>"%s" at %s',
                    $sqlQueryPattern,
                    implode(';' . PHP_EOL, $filesWithThatToken)
                ));
                
                $issue = new Issue();
                IssueHandler::addIssue($issue->setCheckName($this->name)
                        ->setType('sql')
                        ->setComment($sqlQueryPattern));
                
//                $this->issueHandler->addFilesForIssue($filesWithThatToken);
//                $this->issueHandler->addIssue($this->name, 'sql', 
//                        $sqlQueryPattern);
//                $this->issueHandler->save();
                
                $foundTokens = $foundTokens + count($filesWithThatToken);
            }
            Logger::setResultValue($extensionPath, $this->name, $sqlQueryPattern, count($filesWithThatToken));
        }
        return $foundTokens;
    }
}