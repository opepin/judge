<?php
class SQL_Sniffs_RawSQL_DeleteSniff extends SQL_Sniffs_RawSQL_SniffAbstract
{
    protected $_accuratePattern = '/DELETE\s+(LOW_PRIORITY\s+)?(QUICK\s+)?(IGNORE\s+)?FROM\s+/i';
}
