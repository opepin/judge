<?php
class DangerousFunctions_Sniffs_DangerousFunctions_HeaderSniff
    extends DangerousFunctions_Sniffs_DangerousFunctions_SniffAbstract
{
    protected $_exactForbidden = array(
        'header',
        'http_response_code',
    );
}
