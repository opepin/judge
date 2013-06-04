<?php
namespace Netresearch\Plugin;

use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;

/**
 * Base class for plugins
 */
class CodeSniffer extends PluginAbstract
{
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
        } catch(\Exception $e) {
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
