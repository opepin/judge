<?php
class Functions_Sniffs_AvoidableCalls_SocketSniff
    extends Functions_Sniffs_AvoidableCalls_SniffAbstract
{
    protected $_exactForbidden = array(
        'fsockopen',
        'pfsockopen',
    );

    protected $_patternForbidden = array(
        'socket_',
    );
}
