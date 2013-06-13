<?php
class Functions_Sniffs_AvoidableCalls_CookieSniff
    extends Functions_Sniffs_AvoidableCalls_SniffAbstract
{
    protected $_exactForbidden = array(
        'setcookie',
        'setrawcookie',
    );
}
