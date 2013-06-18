<?php
class Functions_Sniffs_AvoidableCalls_RequireOnceSniff implements PHP_CodeSniffer_Sniff
{
    public function register()
    {
        return array(T_REQUIRE_ONCE);
    }
    
    protected function _getPath(PHP_CodeSniffer_File $phpcsFile)
    {
        return str_replace('\\', '/', $phpcsFile->getFilename());
    }

    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if (preg_match('/app\/code\/.+\/controllers\/.+Controller.php/', $this->_getPath($phpcsFile)) !== 1) {
            $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
        }
    }
}
