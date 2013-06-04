<?php
class CoreHacks_Sniffs_Class_OverrideSniff implements PHP_CodeSniffer_Sniff {

    protected $_forbidden = array(
        'Mage_',
        'Enterprise_',
    );
    
    public function register()
    {
        return array(T_CLASS);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        $classNameId = $phpcsFile->findNext(T_STRING, $stackPtr, null, false, null, true);
        if ($classNameId !== false) {
            foreach ($this->_forbidden as $prefix) {
                if (substr($tokens[$classNameId]['content'], 0, strlen($prefix)) == $prefix) {
                    $phpcsFile->addError($tokens[$classNameId]['content'], $stackPtr);
                    break;
                }
            }
        }
    }
}
?>
