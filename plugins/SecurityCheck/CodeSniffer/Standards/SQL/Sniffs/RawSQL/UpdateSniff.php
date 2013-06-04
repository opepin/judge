<?php
class SQL_Sniffs_RawSQL_UpdateSniff extends SQL_Sniffs_RawSQL_SniffAbstract
{
    protected $_accuratePattern = array(
        '/UPDATE\s+.+\s+SET\s+/i',
        '/UPDATE\s+(.+\s+)+SET\s+/i',
    );
    protected $_approximatePattern = '/UPDATE\s+/i';
}