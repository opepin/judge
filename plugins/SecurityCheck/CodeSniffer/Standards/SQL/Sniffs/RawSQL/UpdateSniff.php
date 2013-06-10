<?php
class SQL_Sniffs_RawSQL_UpdateSniff extends SQL_Sniffs_RawSQL_SniffAbstract
{
    protected $_accuratePattern = array(
        '/(?<!ON)\s*UPDATE\s+.+\s+SET\s+/i',
        '/(?<!ON)\s*UPDATE\s+(.+\s+)+SET\s+/i',
    );
    protected $_approximatePattern = '/(?<!ON)\s*UPDATE\s+/i';
}