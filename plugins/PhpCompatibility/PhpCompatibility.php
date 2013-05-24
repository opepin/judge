<?php
namespace PhpCompatibility;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue;
use Netresearch\Plugin as Plugin;

/**
 * check PHP compatibility
 */
class PhpCompatibility extends Plugin
{
    protected $_rewrites=array();

    public function execute($extensionPath)
    {
        parent::execute($extensionPath);

        $options = array(
            'recursive' => true,
            'report'    => 'summary'
        );

        $min         = 0;
        $minReadable = 0;
        $max         = INF;
        $maxReadable = 'latest';

        try {
            $phpci = new \PHP_CompatInfo($options);
            $phpci->parse($extensionPath);

            $allResultsAtOnce = $phpci->toArray();
            foreach ($phpci->toArray() as $file => $result) {
                if ($file == 'versions') {
                    $currentMin = $this->_getVersionInt($result[0]);
                    $currentMax = $this->_getVersionInt($result[1]);

                    if (false == is_null($currentMin) && $min < $currentMin)
                    {
                        $min = $currentMin;
                        $minReadable = $result[0];
                    }
                    if (false == is_null($currentMax) && $currentMax < $max)
                    {
                        $max = $currentMax;
                        $maxReadable = $result[1];
                    }
                }
            }

        } catch (\PHP_CompatInfo_Exception $e) {
            die ('PHP_CompatInfo Exception : ' . $e->getMessage() . PHP_EOL);
        }

        if ($min <= $this->_getVersionInt($this->_settings->min) && $maxReadable=='latest') {
           IssueHandler::addIssue(new Issue( array(
               "extension"  =>  $extensionPath,
               "checkname"  => $this->_pluginName,
               "type"       => 'php_compatibility',
               "comment"    => vsprintf('Extension is compatible to PHP from version %s up to latest versions',
                array($minReadable)),
                "failed"    => false
            )));
            return ;
        }
        IssueHandler::addIssue(new Issue( array(
            "extension" => $extensionPath,
            "checkname" => $this->_pluginName,
            "type"      => 'php_compatibility',
            "comment"   => vsprintf('Extension is compatible to PHP from version %s (instead of required %s) up to %s',
            array($minReadable, $this->_settings->min, $maxReadable)),
            "failed"    => true
        )));
    }

    protected function _getVersionInt($version)
    {
        if (strlen($version)) {
            list($major, $minor, $revision) = explode('.', $version);
            return 10000*$major + 100*$minor + $revision;
        }
        return null;
    }
}
