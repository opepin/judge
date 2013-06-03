<?php
class Output_Sniffs_Dump_VarDumpSniff implements PHP_CodeSniffer_Sniff {

    protected $_forbidden = 'var_dump';
    
    public function register()
    {
        return array(T_STRING);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if ($tokens[$stackPtr]['content'] == $this->_forbidden 
            && $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr) ) {
            
            $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
        }
    }
}
?>
