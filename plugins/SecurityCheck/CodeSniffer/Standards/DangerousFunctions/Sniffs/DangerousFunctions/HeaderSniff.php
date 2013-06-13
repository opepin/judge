<?php
class DangerousFunctions_Sniffs_DangerousFunctions_HeaderSniff implements PHP_CodeSniffer_Sniff {

    protected $_forbidden = array(
        'header',
        'http_response_code',
    );

    public function register()
    {
        return array(T_STRING);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if (in_array($tokens[$stackPtr]['content'], $this->_forbidden) && 
            $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr, null, false, null, true) !== false) {
            
            $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
        }
    }
}
?>
