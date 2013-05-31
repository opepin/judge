<?php
class DieExit_Sniffs_DieExit_DieExitSniff implements PHP_CodeSniffer_Sniff {

    public function register()
    {
        return array(T_EXIT);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
    }
}
?>