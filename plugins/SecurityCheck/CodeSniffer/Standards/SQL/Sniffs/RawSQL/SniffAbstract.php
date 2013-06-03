<?php

abstract class SQL_Sniffs_RawSQL_SniffAbstract implements PHP_CodeSniffer_Sniff
{
    /**
     * Accurate pattern for whole SQL statement
     * @var string
     */
    protected $_accuratePattern;

    /**
     * Approximate pattern for partial SQL statement
     * @var string
     */
    protected $_approximatePattern;

    /**
     * Listened tokens
     * @var array
     */
    protected $_tokens = array(T_CONSTANT_ENCAPSED_STRING, T_DOUBLE_QUOTED_STRING);

    /**
     * Registers the tokens that this sniff wants to listen for
     *
     * @return array
     */
    public function register()
    {
        return $this->_tokens;
    }

    /**
     * Called when one of the token types that this sniff is listening for
     * is found.
     *
     * @param PHP_CodeSniffer_File $phpcsFile
     * @param int $stackPtr
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();
        if (
            // Check for a accurate pattern
            preg_match($this->_accuratePattern, $tokens[$stackPtr]['content'])
            // or if SQL statement is partitioned try to combine query and check result again
            || $this->_approximatePattern
            && preg_match($this->_approximatePattern, $tokens[$stackPtr]['content'])
            && ($statement = $this->_combineStatement($phpcsFile, $stackPtr))
            && preg_match($this->_accuratePattern, $statement)
        ) {
            $phpcsFile->addError($this->_getIssueName(), $stackPtr);
        }
    }

    /**
     * Combine partitioned statement
     *
     * @param PHP_CodeSniffer_File $csFile
     * @param int $stackPtr
     * @param null|string  $varName
     * @return mixed
     */
    protected function _combineStatement(PHP_CodeSniffer_File $csFile, $stackPtr, $varName = null)
    {
        $tokens = $csFile->getTokens();
        $emptyTokens = PHP_CodeSniffer_Tokens::$emptyTokens;

        // try to define name of variable which a statement is assigned to
        if (is_null($varName)
            && ($pos = $csFile->findPrevious(T_EQUAL, $stackPtr - 1, null, false, null, true)) !== false
            && ($pos = $csFile->findPrevious(T_VARIABLE, $pos - 1, null, false, null, true)) !== false) {
            $varName = $tokens[$pos]['content'];
        }

        $semicolonPos = $csFile->findNext(array(T_SEMICOLON), $stackPtr);
        $statementTokens = array_slice($tokens, $stackPtr, $semicolonPos - $stackPtr);
        $statementTokens = array_filter($statementTokens, function($item) use ($emptyTokens) {
            return !in_array($item['code'], $emptyTokens);
        });

        // statement's parts concatenation
        $statementTokens = array_values($statementTokens);
        for ($i = 1; isset($statementTokens[$i]); $i++) {
            if ($statementTokens[$i]['type'] === 'T_STRING_CONCAT'
                && $this->_isStringToken($statementTokens, $i - 1)
                && ($this->_isStringToken($statementTokens, $i + 1)
                    || $this->_isVarToken($statementTokens, $i + 1))
            ) {
                // do the concatenation operation, and merge right side token with left side
                $statementTokens[$i] = $statementTokens[$i - 1];
                $statementTokens[$i]['content'] = $this->_concatStrings(
                    $statementTokens[$i - 1]['content'],
                    $statementTokens[$i + 1]['content']
                );
                unset($statementTokens[$i - 1], $statementTokens[$i + 1]);
                $statementTokens = array_values($statementTokens);
                $i--;
            }
        }
        $statementTokens = array_values($statementTokens);

        // in case SQL statement is partitioned using ".=" operation call this method for a next part
        if ($varName
            && ($pos = $csFile->findNext(T_VARIABLE, $semicolonPos + 1, null, false, $varName, true)) !== false
            && ($pos = $csFile->findNext(T_CONCAT_EQUAL, $pos + 1, null, false, null, true)) !== false
            && ($pos = $csFile->findNext($emptyTokens, $pos + 1, null, true, null, true)) !== false
        ) {
            $statementTokens[0]['content'] = $this->_concatStrings(
                $statementTokens[0]['content'],
                $this->_combineStatement($csFile, $pos, $varName)
            );
        }

        return $statementTokens[0]['content'];
    }

    /**
     * Check is corresponded token is a string token
     *
     * @param array $tokens
     * @param int $i
     * @return bool
     */
    protected function _isStringToken(array $tokens, $i)
    {
        return isset($tokens[$i]) && in_array($tokens[$i]['code'], PHP_CodeSniffer_Tokens::$stringTokens);
    }


    /**
     * Check is corresponded token is a variable token
     *
     * @param array $tokens
     * @param int $i
     * @return bool
     */
    protected function _isVarToken(array $tokens, $i)
    {
        return isset($tokens[$i]) && $tokens[$i]['code'] === T_VARIABLE;
    }

    /**
     * Concat stings (strip extra quotes, conact and add them again)
     *
     * @param string $str1
     * @param string $str2
     * @return string
     */
    protected function _concatStrings($str1, $str2)
    {
        return "'" . trim($str1, '\'"') . trim($str2, '\'"$') . "'";
    }


    /**
     * Define name of issue
     *
     * @return string
     */
    protected function _getIssueName()
    {
        $name = substr(get_class($this), 0, -5);
        return substr($name, strrpos($name, '_') + 1);
    }
}
?>
