<?php
class Functions_Sniffs_AvoidableCalls_IncludeRequireSniff implements PHP_CodeSniffer_Sniff
{
    public function register()
    {
        return array(T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE);
    }

    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
        
    }
}
