<?php
class Output_Sniffs_UnescapedOutput_EchoPrintSniff implements PHP_CodeSniffer_Sniff {

    public function register()
    {
        return array(T_PRINT, T_ECHO);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $phpcsFile->addError('Output construction "' . $tokens[$stackPtr]['content'] .
            '" found on line ' . $tokens[$stackPtr]['line'], $stackPtr);
    }
}
?>
