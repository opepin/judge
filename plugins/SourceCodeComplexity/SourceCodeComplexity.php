<?php
namespace SourceCodeComplexity;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Plugin\Plugin as Plugin;

class SourceCodeComplexity extends Plugin
{
    CONST PHP_MESS_DETECTOR_COMMAND = 'vendor/phpmd/phpmd/src/bin/phpmd';

    CONST PHP_DEPEND_COMMAND = 'vendor/pdepend/pdepend/src/bin/pdepend';

    CONST PHP_DEPEND_TEMP_FILE_NAME_FORMAT = 'php_depend_%s.xml';

    CONST PHP_CPD_MIN_LINES = 5;

    CONST PHP_CPD_MIN_TOKENS = 70;

    private static $_usedMetrics = array('ccn', 'ccn2');

    /**
     * Execute the SourceCodeComplexity plugin
     */
    protected function _execute()
    {
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
        $options = array(
            // path to extension to analyze
            $this->_extensionPath,
            // report view
            'xml',
            // path to standards
            __DIR__ . '/PhpMessDetector/ruleset.xml'
        );
        $mdResults = $this->_executePhpCommand($options);
        if (!($xml = $this->_simplexml_load(implode('', $mdResults)))
            || !($files = $xml->xpath('file'))) {
            return;
        }

        $issues = array();
        foreach ($files as $file) {
            $filename = (string)$file->attributes()->name;
            $violations = $file->xpath('violation');
            if (!$violations) {
                continue;
            }
            foreach ($violations as $violation) {
                $message = (string)$violation;
                $lineNumber = (string)$violation->attributes()->beginline;
                if (!array_key_exists($message, $issues)) {
                    $issues[$message] = array();
                }
                $issues[$message][] = $filename . ':' . $lineNumber;
            }
        }

        foreach ($issues as $message => $files) {
            $occurrences = count($files);
            $files = array_unique($files);
            sort($files);
            $this->_addIssue(array(
                'type'        => 'mess_detector',
                'files'       => $files,
                'comment'     => trim($message),
                'occurrences' => $occurrences,
            ));
        }
    }

    /**
     * Checks the extension with PHP Depend
     */
    protected function _checkWithPhpDepend()
    {
        $this->setExecCommand(self::PHP_DEPEND_COMMAND);
        $tmpFilePath = $this->_config->getTempDirPath()
            . sprintf(self::PHP_DEPEND_TEMP_FILE_NAME_FORMAT, uniqid());
        $this->_executePhpCommand(array('summary-xml' => $tmpFilePath));
        $xml = $this->_simplexml_load(file_get_contents($tmpFilePath));
        unlink($tmpFilePath);
        if (!$xml) {
            return;
        }
        if (($lloc = (int)$xml->attributes()->lloc) === 0) {
            $lloc = 1;
        }
        foreach (self::$_usedMetrics as $metric) {
            $this->_addIssue(array(
                'type'    => $metric,
                'comment' => round(((float)$xml->attributes()->$metric)/$lloc, 2),
            ));
        }
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
        } catch (\Exception $e) {
            return ;
        }

        $this->_addIssue(array(
            'type'        => 'duplicated_code',
            'files'       => $clones->getFilesWithClones(),
            'comment'     => round((float)$clones->getPercentage(), 2),
            'occurrences' => $clones->count()
        ));
    }
}
