<?php
namespace SourceCodeComplexity;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin\PluginAbstract as Plugin;

class SourceCodeComplexity extends Plugin
{
    
    CONST PHP_MESS_DETECTOR_COMMAND = 'vendor/phpmd/phpmd/src/bin/phpmd';
    
    CONST PHP_DEPEND_COMMAND = 'vendor/pdepend/pdepend/src/bin/pdepend';
    
    CONST PHP_DEPEND_TEMP_FILE_NAME = 'temp.xml';
    
    CONST PHP_CPD_MIN_LINES = 5;
    
    CONST PHP_CPD_MIN_TOKENS = 70;
    
    private static $_usedMetrics = array('ccn', 'ccn2');
    /**
     * Checker entry point
     * 
     * @param string $extensionPath the path to the extension to check
     */
    public function execute($extensionPath)
    {
        parent::execute($extensionPath);
        $this->_checkWithPhpMessDetector();
        $this->_checkWithPhpCopyPasteDetector();
        $this->_checkWithPhpDepend();
    }

    /**
     * Checks the extension with phpMessDetector 
     */
    protected function _checkWithPhpMessDetector()
    {
        $this->setExecCommand(self::PHP_MESS_DETECTOR_COMMAND);
        $addionalParams = array(
            // path to extension to analyze
            $this->_extensionPath,
            // report view
            'xml',
            // path to standards
            __DIR__ . '/PhpMessDetector/ruleset.xml'
        );
        $mdResults = $this->_executePhpCommand($this->_config, $addionalParams);
        $parsedResult = $this->_parsePhpMdResults($mdResults);
        $this->_addIssues($parsedResult, 'mess_detector');
    }
    
    /**
     * Parses results provided by PHP Mess Detector into array with predefined structure
     * 
     * @param array $phpmdOutput
     * @return array 
     */
    protected function _parsePhpMdResults($phpmdOutput)
    {
        $result = array();
        $phpmdOutput = implode('', $phpmdOutput);
        
        try {
            $xml = simplexml_load_string($phpmdOutput);
        } catch(\Exception $e) {
            return $result;
        }
        $files = $xml->xpath('file');
        if (!$files) {
            return $result;
        }
        foreach ($files as $file) {
            $filename = (string)$file->attributes()->name;
            $violations = $file->xpath('violation');
            if (!$violations) {
                continue;
            }
            foreach ($violations as $violation) {
                $type = (string)$violation;
                $lineNumber = (string)$violation->attributes()->beginline;
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
                'comment'     => trim($type),
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
    protected function _addIssues($issues, $type)
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
    
    /**
     * Checks the extension with PHP Depend 
     */
    protected function _checkWithPhpDepend()
    {
        $this->setExecCommand(self::PHP_DEPEND_COMMAND);
        $params = array('summary-xml' => self::PHP_DEPEND_TEMP_FILE_NAME);
        $this->_executePhpCommand($this->_config, $params);
        try {
            $xml = simplexml_load_file(self::PHP_DEPEND_TEMP_FILE_NAME);
        } catch (Exception $e) {
            unlink(self::PHP_DEPEND_TEMP_FILE_NAME);
            return;
        }
        $lloc = (int)$xml->attributes()->lloc > 0 ? (int)$xml->attributes()->lloc : 1;
        foreach (self::$_usedMetrics as $metric) {
            $issue = array(array(
                'files'       => '',
                'comment'     => round(((float)$xml->attributes()->$metric)/$lloc, 2),
                'occurrences' => 1,
            ));
            $this->_addIssues($issue, $metric);
        }
        unlink(self::PHP_DEPEND_TEMP_FILE_NAME);
    }
    
    /**
     * Checks the extension with php copy and paste detector
     */
    protected function _checkWithPhpCopyPasteDetector()
    {
        $facade = new \File_Iterator_Facade;
        $files = $facade->getFilesAsArray($this->_extensionPath);

        $strategy = new \PHPCPD_Detector_Strategy_Default();
        $detector = new \PHPCPD_Detector($strategy, null);
        
        try{
            $clones = $detector->copyPasteDetection($files, self::PHP_CPD_MIN_LINES, self::PHP_CPD_MIN_TOKENS);
        } catch (Exception $e) {
            return ;
        }
        $issue = array(array(
            'files'       => $clones->getFilesWithClones(),
            'comment'     => $clones->getPercentage(),
            'occurrences' => $clones->count()
        ));

        $this->_addIssues($issue, 'duplicated_code');
    }
}
