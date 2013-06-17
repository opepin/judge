<?php
namespace Netresearch\Plugin;

/**
 * Base class for plugins
 */
abstract class CodeSniffer extends Plugin
{
    /**
     * Execute PHP code from command line
     *
     * @param array $additionalOptions
     * @return array
     */
    protected function _executePhpCommand(array $additionalOptions)
    {
        // set option report format, _parsePhpCsResult method depends on checkstyle
        $additionalOptions['report'] = 'checkstyle';
        return parent::_executePhpCommand($additionalOptions);
    }

    /**
     * Parses php code sniffer checkstyle formatted xml into array with predefined structure
     *
     * @param array $phpcsOutput
     * @param string $commentFormat
     * @param array|string $sourceToListen
     * @param string $severityToListen 'warning'|'error'
     * @return array
     */
    protected function _parsePhpCsResult($phpcsOutput, $commentFormat = 'Issue %s found',
                                         $sourceToListen = array(), $severityToListen = null)
    {
        $result = array();
        $sourceToListen = (array) $sourceToListen;
        $phpcsOutput = implode('', $phpcsOutput);
        $commentFormat = (array)$commentFormat;
        $defaultComment = array_shift($commentFormat);

        if (!($xml = $this->_simplexml_load($phpcsOutput))
            || !($files = $xml->xpath('file'))) {
            return $result;
        }
        foreach ($files as $file) {
            $filename = (string)$file->attributes()->name;
            $errors = $file->xpath('error');
            if (!$errors) {
                continue;
            }
            foreach ($errors as $error) {
                $source = (string) $error->attributes()->source;
                $severity = (string) $error->attributes()->severity;
                // Ignoring all issues except specified in $sourceToListen or $severityToListen
                if (!empty($sourceToListen) && !in_array($source, $sourceToListen)
                    || !empty($severityToListen) && $severity !== $severityToListen) {
                    continue;
                }
                $message = $this->_parsePhpCsMessage((string) $error->attributes()->message);
                if (!array_key_exists($source, $result)) {
                    $result[$source] = array('message' => $message, 'files' => array());
                }
                $result[$source]['files'][] = $filename . ':' . (string) $error->attributes()->line;
            }
        }
        
        $issues = array();
        foreach ($result as $source => $entry) {
            $occurrences = count($entry['files']);
            $files = array_unique($entry['files']);
            sort($files);
            $messageCommentFormat = array_key_exists($source, $commentFormat) ? $commentFormat[$source] : $defaultComment;
            $issues[] = array(
                'files'       => $files,
                'comment'     => sprintf($messageCommentFormat, $entry['message']),
                'occurrences' => $occurrences,
            );
        }
        return $issues;
    }

    /**
     * Adds issue to result with specified type, comment, [files] and [occurrences]
     *
     * @param array $issues
     * @param string|null $type
     */
    protected function _addPhpCsIssues($issues, $type = null)
    {
        foreach ($issues as $issue) {
            if (!is_null($type)) {
                $issue['type'] = $type;
            }
            $this->_addIssue($issue);
        }
    }

    /**
     * Parse issue message
     *
     * @param string $message
     * @return string
     */
    protected function _parsePhpCsMessage($message)
    {
        return $message;
    }
}
