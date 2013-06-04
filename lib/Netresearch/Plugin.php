<?php
namespace Netresearch;

use Netresearch\Config;
use Netresearch\IssueHandler;

/**
 * Base class for plugins
 */
abstract class Plugin implements PluginInterface
{
    const OCCURRENCES_LIST_PREFIX = '  * ';
    const OCCURRENCES_LIST_SUFFIX = PHP_EOL;

    protected $_phpBin;
    /**
     * Execution command
     * @var string
     */
    protected $_execCommand;

    /**
     * Plugin name, same as check class name
     * @var string
     */
    protected $_pluginName;

    /**
     * Path to extension source
     * @var string
     */
    protected $_extensionPath;

    /**
     * The global Judge configuration
     * @var \Netresearch\Config
     */
    protected $_config;
    /**
     * The local plugin configuration
     * @var \Zend_Config
     */
    protected $_settings;

    /**
     * Base constructor for all plugins
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->_config = $config;
        $this->_pluginName = current(explode('\\', get_class($this)));
        $this->_settings = $this->_config->plugins->{$this->_pluginName};
    }

    /**
     * Execute a plugin (entry point)
     *
     * @param string $extensionPath the path to the extension to check
     */
    public function execute($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
    }


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
     * @throws \Zend_Exception
     */
    protected function _executeCommand($command)
    {
        //Logger::log($command);
        exec($command, $response, $status);

        if ($status == 255) {
            $this->setUnfinishedIssue();
            throw new \Zend_Exception('Failed to execute ' . $this->_pluginName .' plugin.');
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
    
    /**
     * Parses php code sniffer execution xml into array with predifined structure
     * 
     * @param array  $phpcsOutput
     * @param string $comment
     * @param array $sniffsToListen
     * @return array 
     */    
    protected function _parsePhpCsResult($phpcsOutput, $comment = 'Issue %s found', $sniffsToListen = array())
    {
        $result = array();
        if (!is_array($sniffsToListen) || empty($sniffsToListen)) {
            return $result;
        }
        $phpcsOutput = implode('', $phpcsOutput);
        
        try {
            $xml = simplexml_load_string($phpcsOutput);
        } catch(Exception $e) {
            return $result;
        }
        $files = $xml->xpath('file');
        if (!$files) {
            return $result;
        }
        foreach ($files as $file) {
            $filename = (string)$file->attributes()->name;
            $errors = $file->xpath('error');
            if (!$errors) {
                continue;
            }
            foreach ($errors as $error) {
                // Ignoring all sniffs except specified in $sniffsToListen
                if (!in_array((string)$error->attributes()->source, $sniffsToListen)) {
                    continue;
                }
                $type = (string)$error->attributes()->message;
                $lineNumber = (string)$error->attributes()->line;
                if (!array_key_exists($type, $result)) {
                    $result[$type] = array();
                }
                $result[$type][] = $filename . ':' . $lineNumber;
            }
        }
        
        $return = array();
        foreach ($result as $type => $files) {
            $occurences = count($files);
            $files = array_unique($files);
            sort($files);
            $return[] = array(
                'files'       => $files,
                'comment'     => sprintf($comment, $type),
                'occurrences' => $occurences,
            );
        }
        return $return;
    }
    
    /**
     * Adds issue(s) to result with specified comment, files, occurrences, type
     * 
     * @param array $issues
     * @param string $type 
     */
    protected function _addPhpCsIssues($issues, $type)
    {
        foreach ($issues as $issue) {
            IssueHandler::addIssue(new Issue( array( 
                "extension"   => $this->_extensionPath,
                "checkname"   => $this->_pluginName,
                "type"        => $type,
                "comment"     => $issue['comment'],
                "files"       => $issue['files'],
                "occurrences" => $issue['occurrences'],
            )));
        }
    }    
}
