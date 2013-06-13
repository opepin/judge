<?php
class DangerousFunctions_Sniffs_DangerousFunctions_ShellSniff
    extends DangerousFunctions_Sniffs_DangerousFunctions_SniffAbstract
{
    protected $_exactForbidden = array(
        'exec',
        'shell_exec',
        'popen',
        'system',
        'passthru',
    );

    protected $_patternForbidden = array(
        'pcntl_',
        'proc_',
    );
}
