<?php
class Output_Sniffs_Dump_VarExportPrintRSniff implements PHP_CodeSniffer_Sniff {

    protected $_forbidden = array('var_export', 'print_r');
    
    public function register()
    {
        return array(T_STRING);
    }
    
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if (in_array($tokens[$stackPtr]['content'], $this->_forbidden)) {
            $bracketsStart = $phpcsFile->findNext(T_OPEN_PARENTHESIS, $stackPtr, null, false, null, true);
            if ($bracketsStart !== false && isset($tokens[$bracketsStart]['parenthesis_closer'])) {
                $bracketsEnd = $tokens[$bracketsStart]['parenthesis_closer'];
                $commaId = $phpcsFile->findNext(T_COMMA, $bracketsStart, $bracketsEnd);
                if ($commaId === false) {
                    // there is only one param in function call, adding error
                    $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
                } else {
                    /* there are more than one param in function call.
                     if second param is NULL, FALSE, 0(ZERO) or empty string adding error */
                    $paramId = $phpcsFile->findNext(
                        array(
                            T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING, T_FALSE, T_NULL, T_DNUMBER, T_LNUMBER),
                        $commaId,
                        $bracketsEnd
                    );
                    if ($paramId !== false) {
                        switch ($tokens[$paramId]['type']) {
                            case 'T_FALSE':
                            case 'T_NULL':
                                $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
                                break;
                            case 'T_DNUMBER':
                            case 'T_LNUMBER':
                                if (abs($tokens[$paramId]['content']) < 0.00000001) {
                                    $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
                                }
                                break;
                            case 'T_CONSTANT_ENCAPSED_STRING':
                                if (strlen(trim($tokens[$paramId]['content'], '\'"')) == 0) {
                                    $phpcsFile->addError($tokens[$stackPtr]['content'], $stackPtr);
                                }
                                break;
                        }
                    }
                }
            }
        }
    }
}
?>
