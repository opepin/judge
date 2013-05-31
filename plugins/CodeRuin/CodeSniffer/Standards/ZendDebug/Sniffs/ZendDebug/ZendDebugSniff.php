<?php
class ZendDebug_Sniffs_ZendDebug_ZendDebugSniff implements PHP_CodeSniffer_Sniff {

    protected $_searchedClass = 'Zend_Debug';
    
    protected $_searchedMethod = 'dump';
    
    public function register()
    {
        return array(T_DOUBLE_COLON);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $previousTokenId = $phpcsFile->findPrevious(T_STRING, $stackPtr);
        $nextTokenId = $phpcsFile->findNext(T_STRING, $stackPtr);
        if ($tokens[$previousTokenId]['content'] == $this->_searchedClass &&
            $tokens[$nextTokenId]['content'] == $this->_searchedMethod) {
            
            $phpcsFile->addError($this->_searchedClass . '::' . $this->_searchedMethod, $previousTokenId);
        }
    }
}
?>