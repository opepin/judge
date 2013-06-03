<?php
class Output_Sniffs_Dump_VarExportSniff implements PHP_CodeSniffer_Sniff {

    protected $_forbidden = 'var_export';
    
    public function register()
    {
        return array(T_STRING);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if ($tokens[$stackPtr]['content'] == $this->_forbidden
            && $bracketsStart = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr)) {
            
            $bracketsEnd = isset($tokens[$bracketsStart]['parenthesis_closer']) ? 
                $tokens[$bracketsStart]['parenthesis_closer'] : null;
            
            if ($phpcsFile->findNext(T_COMMA, $bracketsStart, $bracketsEnd) !== false) {
                $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
            }
            
        }
    }
}
?>
