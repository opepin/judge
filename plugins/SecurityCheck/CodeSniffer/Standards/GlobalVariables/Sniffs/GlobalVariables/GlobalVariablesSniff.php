<?php
class GlobalVariables_Sniffs_GlobalVariables_GlobalVariablesSniff implements PHP_CodeSniffer_Sniff {

    protected $_permitted = array(
        '$_POST',
        '$_GET',
        '$_REQUEST',
        '$_SERVER',
        '$_COOKIE',
        '$_SESSION',
        '$_ENV',
        '$GLOBALS',
    );
    
    public function register()
    {
        return array(T_VARIABLE);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if (in_array($tokens[$stackPtr]['content'], $this->_permitted)) {
            $phpcsFile->addError(
                'Global variable "' . $tokens[$stackPtr]['content'] . '" found on line ' . $tokens[$stackPtr]['line'],
                $stackPtr
            );
        }
        
    }
}
?>
