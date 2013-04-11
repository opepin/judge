<?php
namespace Netresearch;

use Netresearch\Config;
use Netresearch\IssueHandler;

/**
 * Base class for plugins
 */
class Plugin
{
    protected $_phpBin;
    protected $_execCommand;
    protected $_pluginName;
    protected $_extensionPath;

    /**
     * @param Config $config
     * @param array $additionalOptions
     * @return array
     */
    protected function _executePhpCommand(Config $config, array $additionalOptions)
    {
        exec('which php', $response);
        $this->_phpBin = reset($response);

        if (!empty($config->phpOptions)) {
            foreach ($config->phpOptions as $option) {
                $this->_phpBin .= ' -d ' . $option;
            }
        }

        if (!empty($additionalOptions)) {
            foreach ($additionalOptions as $key => $value) {
                $this->_execCommand .= is_string($key) ? ' --' . $key . '=' . $value
                    : ' ' . $value;
            }
        }

        $command = $this->_phpBin . ' ' . $this->_execCommand;
        $command .= !in_array($this->_extensionPath, $additionalOptions) ? ' ' . $this->_extensionPath : '';

        return $this->_executeCommand($command);
    }

    /**
     * @param string $command
     * @return array
     * @throws Exception
     */
    protected function _executeCommand($command)
    {
        //Logger::log($command);
        exec($command, $response, $status);

        if ($status == 255) {
            $this->setUnfinishedIssue();
            throw new \Exception('Failed to execute ' . $this->_pluginName .' plugin.');
        }

        return $response;
    }

    /**
     *
     */
    public function setUnfinishedIssue($reason = '')
    {
        $message = 'Failed to execute ' . $this->_pluginName .' plugin.';
        // if a specific reason is given, append it to the message
        if (0 < strlen(trim($reason))) {
            $message .= ' reason: ' . $reason;
        }
        IssueHandler::addIssue(new Issue(
            array(
                'extension' =>  $this->_extensionPath,
                'checkname' =>  $this->_pluginName,
                'type'      =>  'unfinished',
                'comment'   =>  $message,
                'failed'    =>  false
            )
        ));
    }



    /**
     * @param $command
     * @return Plugin
     */
    public function setExecCommand($command)
    {
        $this->_execCommand = $command;
        return $this;
    }
}
