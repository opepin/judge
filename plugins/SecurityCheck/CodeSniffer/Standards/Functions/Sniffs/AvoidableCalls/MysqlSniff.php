<?php
class Functions_Sniffs_AvoidableCalls_MysqlSniff
    extends Functions_Sniffs_AvoidableCalls_SniffAbstract
{
    protected $_patternForbidden = array(
        'mysql_',
        'mysqli_',
    );
}
