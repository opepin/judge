<?php
class Functions_Sniffs_AvoidableCalls_ConfigSniff
    extends Functions_Sniffs_AvoidableCalls_SniffAbstract
{
    protected $_exactForbidden = array(
        'set_include_path',
        'restore_include_path',
        'get_include_path',
        'ini_set',        
        'ini_restore',
        'ini_get',
        'ini_get_all',
        'ini_alter',
        'get_cfg_var',
    );
}
