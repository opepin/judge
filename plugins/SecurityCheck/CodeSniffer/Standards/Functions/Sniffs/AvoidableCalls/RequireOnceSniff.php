<?php
class Functions_Sniffs_AvoidableCalls_RequireOnceSniff implements PHP_CodeSniffer_Sniff
{
    public function register()
    {
        return array(T_REQUIRE_ONCE);
    }

    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if (preg_match('/app\/code\/.+\/controllers\/.+Controller.php/', $phpcsFile->getFilename()) !== 1) {
            $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
        }
    }
}
