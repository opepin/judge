<?php
class DangerousFunctions_Sniffs_DangerousFunctions_SocketSniff implements PHP_CodeSniffer_Sniff {

    protected $_exactForbidden = array(
        'fsockopen',
        'pfsockopen',
    );
    
    protected $_patternForbidden = array(
        'socket_',
    );    

    public function register()
    {
        return array(T_STRING);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if ($this->_isForbidden($tokens[$stackPtr]['content']) && 
            $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr, null, false, null, true) !== false) {
            
            $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
        }
    }
    
    protected function _isForbidden($function)
    {
        if (in_array($function, $this->_exactForbidden)) {
            return true;
        }
        foreach ($this->_patternForbidden as $pattern) {
            if (substr($function, 0, strlen($pattern)) == $pattern) {
                return true;
            }
        }
        return false;
    }
}
?>
