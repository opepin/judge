<?php
namespace PerformanceCheck;

use Netresearch\Logger;
use Netresearch\IssueHandler;
use Netresearch\Issue as Issue;
use Netresearch\Plugin\Plugin as Plugin;

class PerformanceCheck extends Plugin
{
    protected $_results;

    /**
     * Execute the PerformanceCheck plugin
     */
    protected function _execute()
    {
        $possiblePerformanceKillers = $this->_scanForPerformanceLeaks($this->_extensionPath);

        if (0 < sizeof($possiblePerformanceKillers)) {
            foreach ($possiblePerformanceKillers as $possiblePerformanceKiller) {
               Logger::setResultValue($this->_extensionPath, $this->_pluginName, $possiblePerformanceKiller, count($possiblePerformanceKillers));
               
               IssueHandler::addIssue(new Issue(
                       array(   "extension"  => $this->_extensionPath ,"checkname" => $this->_pluginName,
                                "type"       => 'performance_leak',
                                "comment"    => $possiblePerformanceKiller . ' (' . 
                           count($possiblePerformanceKillers) . 'times)',
                                "failed"    =>  true)));
            }
        }
    }


    /**
     * @TODO refactor (the same as \MageCompability\Extension::isUnitTestFile)
     */
    protected function _isUnitTestFile($filePath)
    {
        $filePath = str_replace($this->_extensionPath, '', $filePath);
        return (0 < preg_match('~app/code/.*/.*/Test/~u', $filePath));
    }

    /**
     *
     * @TODO: refactor (nearly the same as \MageCompability\Extension::addMethods)
     *
     * @param string $path
     * @return array array of potential performance issues
     */
    protected function _scanForPerformanceLeaks($path)
    {
        $possiblePerformanceLeaks = array();
        $parser = new \PHPParser_Parser(new \PHPParser_Lexer);
        foreach (glob($path . '/*') as $item) {
            if (is_dir($item)) {
                $possiblePerformanceLeaks = array_merge($possiblePerformanceLeaks,$this->_scanForPerformanceLeaks($item));
            }
            if (is_file($item) && is_readable($item)) {
                if ($this->_isUnitTestFile($item)) {
                    continue;
                }
                /* we assume that there are only php files */
                if (substr($item, -6) == '.stmts.xml') {
                    unlink($item); continue;
                }
                $fileNameParts = explode('.', basename($item));
                $extension = end($fileNameParts);
                if (false === in_array($extension, array('php', 'phtml'))) {
                    continue;
                }
                try {
                    $stmts = $parser->parse(file_get_contents($item));
                    $serializer = new \PHPParser_Serializer_XML;
                    $xml = $serializer->serialize($stmts);
//                    file_put_contents($item . '.stmts.xml', var_export($xml, true));
                    $leaks = $this->_collectPerformanceKillers(simplexml_load_string($xml), $item);
                    $possiblePerformanceLeaks = array_merge($possiblePerformanceLeaks, $leaks);
                } catch (\PHPParser_Error $e) {
                    // no valid php
                    continue;
                }
            }
        }
        return $possiblePerformanceLeaks;
    }


    protected function _collectPerformanceKillers($xmlTree, $fileName)
    {
        $possiblePerformanceLeaks = array();
	if ($xmlTree == null) 
        {
            echo $fileName . ' skipped';
            return $possiblePerformanceLeaks;
        }
        $stmts = array('Stmt_Foreach', 'Stmt_For', 'Stmt_While', 'Stmt_Do');
        foreach ($stmts as $stmt) {
            $saveStmtXpath  = "//node:$stmt//node:Expr_MethodCall[subNode:name/scalar:string/text()='save']";
            $saveCalls      = $xmlTree->xpath($saveStmtXpath);
            foreach ($saveCalls as $saveCall) {
                $saveCallLineNumber = current($saveCall->xpath('./attribute:endLine/scalar:int/text()'));
                $encirclingForeach  = $xmlTree->xpath("//node:" . $stmt . "[./attribute:startLine/scalar:int/text() < $saveCallLineNumber and ./attribute:endLine/scalar:int/text() > $saveCallLineNumber]");
                if (0 < count($encirclingForeach)) {
                    $loopStartLine  = current(current($encirclingForeach)->xpath('./attribute:startLine/scalar:int/text()'));
                    $loopEndLine    = current(current($encirclingForeach)->xpath('./attribute:endLine/scalar:int/text()'));
                    $possiblePerformanceLeak = 'save called in a loop in file '
                    . $fileName . ' in line ' . $saveCallLineNumber . ' (loop ' . $stmt . ' starts in line ' . $loopStartLine . ' and ends in line ' . $loopEndLine . ')' ;
                    if (!in_array($possiblePerformanceLeak, $possiblePerformanceLeaks)) {
                        $possiblePerformanceLeaks[] = $possiblePerformanceLeak;
                    }
                }
            }
        }
        $xpathForPerformanceLeaks = "//node:Expr_MethodCall[./subNode:name/scalar:string/text() = 'getItemById']//node:Expr_MethodCall[./subNode:name/scalar:string/text() = 'getCollection']";
        $collectionCalls = $xmlTree->xpath($xpathForPerformanceLeaks);
        if (0 < count($collectionCalls)) {
            $startLine  = current(current($collectionCalls)->xpath('./attribute:startLine/scalar:int/text()'));
            $endLine    = current(current($collectionCalls)->xpath('./attribute:endLine/scalar:int/text()'));
            $possiblePerformanceLeak = 'getCollection()->getItemById() called in file '
                    . $fileName . ' in lines ' . $startLine . '-' . $endLine;
            if (!in_array($possiblePerformanceLeak, $possiblePerformanceLeaks)) {
                        $possiblePerformanceLeaks[] = $possiblePerformanceLeak;
            }
        }
        return $possiblePerformanceLeaks;
    }
}
