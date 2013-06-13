<?php
class DangerousFunctions_Sniffs_DangerousFunctions_SocketSniff
    extends DangerousFunctions_Sniffs_DangerousFunctions_SniffAbstract
{
    protected $_exactForbidden = array(
        'fsockopen',
        'pfsockopen',
    );

    protected $_patternForbidden = array(
        'socket_',
    );
}
