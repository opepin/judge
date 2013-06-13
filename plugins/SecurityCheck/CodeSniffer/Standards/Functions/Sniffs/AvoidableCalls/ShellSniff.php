<?php
class Functions_Sniffs_AvoidableCalls_ShellSniff
    extends Functions_Sniffs_AvoidableCalls_SniffAbstract
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
