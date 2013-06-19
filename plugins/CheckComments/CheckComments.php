<?php
namespace CheckComments;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Plugin\Plugin as Plugin;

class CheckComments extends Plugin
{
    CONST PHP_DEPEND_COMMAND = 'vendor/pdepend/pdepend/src/bin/pdepend';

    CONST PHP_DEPEND_TEMP_FILE_NAME_FORMAT = 'php_depend_comments_%s.xml';

    /**
     * Execute the CheckComments plugin. Entry point
     */
    protected  function _execute()
    {
        $this->_checkWithPhpDepend();
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
        if (($ncloc = (int)$xml->attributes()->ncloc) === 0) {
            $ncloc = 1;
        }
        $this->_addIssue(array(
            'type'    => 'cloc_to_ncloc',
            'comment' => round(((float)$xml->attributes()->cloc)/$ncloc, 2),
        ));        
    }
}
