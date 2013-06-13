<?php
class DangerousFunctions_Sniffs_DangerousFunctions_ConfigSniff implements PHP_CodeSniffer_Sniff {

    protected $_forbidden = array(
        'set_include_path',
        'restore_include_path',
        'get_include_path',
        'ini_set',        
        'ini_restore',
        'ini_get',
        'ini_get_all',
        'ini_alter',
        'get_cfg_var',
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
