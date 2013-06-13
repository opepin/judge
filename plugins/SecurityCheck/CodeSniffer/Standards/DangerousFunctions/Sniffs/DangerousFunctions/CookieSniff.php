<?php
class DangerousFunctions_Sniffs_DangerousFunctions_CookieSniff
    extends DangerousFunctions_Sniffs_DangerousFunctions_SniffAbstract
{
    protected $_exactForbidden = array(
        'setcookie',
        'setrawcookie',
    );
}
