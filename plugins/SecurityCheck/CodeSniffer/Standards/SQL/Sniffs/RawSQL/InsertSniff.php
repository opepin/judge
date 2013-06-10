<?php
class SQL_Sniffs_RawSQL_InsertSniff extends SQL_Sniffs_RawSQL_SniffAbstract
{
    protected $_accuratePattern = '/INSERT\s+((LOW_PRIORITY|DELAYED|HIGH_PRIORITY)\s+)?(IGNORE\s+)?INTO\s+/i';
}