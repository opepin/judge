<?php
class Output_Sniffs_Dump_ZendDebugSniff implements PHP_CodeSniffer_Sniff {

    protected $_forbiddenClass = 'Zend_Debug';
    
    protected $_forbiddenMethod = 'dump';
    
    public function register()
    {
        return array(T_DOUBLE_COLON);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $previousTokenId = $phpcsFile->findPrevious(T_STRING, $stackPtr);
        $nextTokenId = $phpcsFile->findNext(T_STRING, $stackPtr);
        if ($tokens[$previousTokenId]['content'] == $this->_forbiddenClass &&
            $tokens[$nextTokenId]['content'] == $this->_forbiddenMethod) {
            
            $phpcsFile->addError($this->_forbiddenClass . '::' . $this->_forbiddenMethod, $previousTokenId);
        }
    }
}
?>