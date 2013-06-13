<?php
class Functions_Sniffs_AvoidableCalls_MailSniff
    extends Functions_Sniffs_AvoidableCalls_SniffAbstract
{
    protected $_exactForbidden = array(
        'mail',
    );

    protected $_patternForbidden = array(
        'imap_',
    );
}
