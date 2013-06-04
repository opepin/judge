<?php
class SQL_Sniffs_RawSQL_SelectSniff extends SQL_Sniffs_RawSQL_SniffAbstract
{
    protected $_accuratePattern = array(
        '/SELECT\s+.+\s+FROM\s+/i',
        '/SELECT\s+(.+\s+)+FROM\s+/i',
    );
    protected $_approximatePattern = '/SELECT\s+/i';
}
