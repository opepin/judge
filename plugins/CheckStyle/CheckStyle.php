<?php
namespace CheckStyle;

use Netresearch\Logger;
use Netresearch\Plugin\CodeSniffer as Plugin;

class CheckStyle extends Plugin
{
    /**
     * Execution command
     * @var string
     */
    protected $_execCommand = 'vendor/squizlabs/php_codesniffer/scripts/phpcs';

    /**
     * Execute the CheckStyle plugin
     */
    protected function _execute()
    {
        $options = array(
            'ignore'   => '*.js',
            'standard' => __DIR__ . '/CodeSniffer/Standards/Magento',
        );
        $csResults = $this->_executePhpCommand($options);

        $issues = $this->_parsePhpCsResult($csResults, '%s', array(), 'error');
        $this->_addPhpCsIssues($issues, 'error');
        $issues = $this->_parsePhpCsResult($csResults, '%s', array(), 'warning');
        $this->_addPhpCsIssues($issues, 'warning');
    }

    /**
     * Parse issue message
     *
     * @param string $message
     * @return string
     */
    protected function _parsePhpCsMessage($message)
    {
        if (($semicolonPos = strpos($message, ';')) !== false) {
            $message = substr($message, 0, $semicolonPos);
        }
        return $message;
    }
}
