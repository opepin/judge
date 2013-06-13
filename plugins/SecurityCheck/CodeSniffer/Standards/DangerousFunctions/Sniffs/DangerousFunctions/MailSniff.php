<?php
class DangerousFunctions_Sniffs_DangerousFunctions_MailSniff
    extends DangerousFunctions_Sniffs_DangerousFunctions_SniffAbstract
{
    protected $_exactForbidden = array(
        'mail',
    );

    protected $_patternForbidden = array(
        'imap_',
    );
}
