<?php
namespace CodeRuin;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin as Plugin;

class CodeRuin extends Plugin
{
    /**
     *
     * @param string $extensionPath the path to the extension to check
     */
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);
        $this->_extensionContainsTokens($extensionPath, $this->_settings->criticals, 'critical');
        $this->_extensionContainsTokens($extensionPath, $this->_settings->warnings, 'warning');
    }

    protected function _extensionContainsTokens($extensionPath, $tokens, $type)
    {
        foreach ($tokens as $token) {
            $command = 'grep -riEl "' . $token . '" ' . $extensionPath . '/app';
            try {
                $filesWithThatToken = $this->_executeCommand($command);
            } catch (\Zend_Exception $e) {
                return;
            }
            if (count($filesWithThatToken)) {
                IssueHandler::addIssue(new Issue(
                    array('extension' =>  $extensionPath,
                          'checkname' => $this->_pluginName,
                          'type'      => $type,
                          'comment'   => $token,
                          'files'     => $filesWithThatToken,
                          'failed'    =>  true
                    )
                ));
            }
        }
    }
}

