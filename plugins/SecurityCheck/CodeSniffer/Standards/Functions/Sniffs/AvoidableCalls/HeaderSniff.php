<?php
class Functions_Sniffs_AvoidableCalls_HeaderSniff
    extends Functions_Sniffs_AvoidableCalls_SniffAbstract
{
    protected $_exactForbidden = array(
        'header',
        'http_response_code',
    );
}
