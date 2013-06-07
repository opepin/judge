<?php
namespace CheckStyle;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin\PluginAbstract as Plugin;

class CheckStyle extends Plugin
{
    /**
     * Execution command
     * @var string
     */
    protected $_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';

    /**
     * Plugin entry point to check code styling
     * 
     * @param string $extensionPath the path to the extension to check
     */
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);
        $params = array(
            'ignore'   => '*/jquery*',
            'standard' => __DIR__ . '/CodeSniffer/Standards/Magento',
            'report'   => 'checkstyle',
        );

        $csResults = $this->_executePhpCommand($this->_config, $params);
        $parsedResult = $this->_parseCheckStylePhpCsResult($csResults);
        $this->_addCheckStylePhpCsIssues($parsedResult);
    }
    
    /**
     * Parses php code sniffer execution xml into array with predifined structure
     *
     * @param array  $phpcsOutput
     * @return array
     */
    protected function _parseCheckStylePhpCsResult($phpcsOutput)
    {
        $resultErrors = array();
        $resultWarnings = array();
        $messages = array();
        $phpcsOutput = implode('', $phpcsOutput);

        try {
            $xml = simplexml_load_string($phpcsOutput);
        } catch(\Exception $e) {
            return array();
        }
        $files = $xml->xpath('file');
        if (!$files) {
            return array();
        }
        foreach ($files as $file) {
            $filename = (string)$file->attributes()->name;
            $errors = $file->xpath('error');
            if (!$errors) {
                continue;
            }
            foreach ($errors as $error) {
                $message = (string)$error->attributes()->message;
                $lineNumber = (string)$error->attributes()->line;
                $source = (string)$error->attributes()->source;
                if ((string)$error->attributes()->severity == 'warning') {
                    if (!array_key_exists($source, $resultWarnings)) {
                        $resultWarnings[$source] = array();
                    }
                    $resultWarnings[$source][] = $filename . ':' . $lineNumber;
                } else {
                    if (!array_key_exists($source, $resultErrors)) {
                        $resultErrors[$source] = array();
                    }
                    $resultErrors[$source][] = $filename . ':' . $lineNumber;
                }
                if (!array_key_exists($source, $messages)) {
                    $message = strpos($message, ';') !== false ? substr($message, 0, strpos($message, ';')) : $message;
                    $messages[$source] = $message;
                }
            }
        }

        $return = array();
        foreach ($resultWarnings as $type => $files) {
            $occurences = count($files);
            $files = array_unique($files);
            sort($files);
            $return[] = array(
                'type'        => 'warning',
                'files'       => $files,
                'comment'     => $messages[$type],
                'occurrences' => $occurences,
            );
        }          
        foreach ($resultErrors as $type => $files) {
            $occurences = count($files);
            $files = array_unique($files);
            sort($files);
            $return[] = array(
                'type'        => 'error',
                'files'       => $files,
                'comment'     => $messages[$type],
                'occurrences' => $occurences,
            );
        }
        return $return;
    }
    
    /**
     * Adds issue(s) to result with specified comment, files, occurrences, type
     *
     * @param array $issues
     */
    protected function _addCheckStylePhpCsIssues($issues)
    {
        foreach ($issues as $issue) {
            IssueHandler::addIssue(new Issue( array(
                "extension"   => $this->_extensionPath,
                "checkname"   => $this->_pluginName,
                "type"        => $issue['type'],
                "comment"     => $issue['comment'],
                "files"       => $issue['files'],
                "occurrences" => $issue['occurrences'],
            )));
        }
    }    
}
