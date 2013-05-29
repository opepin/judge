<?php
class Output_Sniffs_UnescapedOutput_VarDumpExportSniff implements PHP_CodeSniffer_Sniff {

    protected $_permitted = array('var_dump', 'var_export');
    
    public function register()
    {
        return array(T_STRING);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if (in_array($tokens[$stackPtr]['content'], $this->_permitted) 
            && $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr) ) {
            
            $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
        }
    }
}
?>
