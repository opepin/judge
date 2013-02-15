<?php
/**
 * Description of ExtensionFilter
 *
 * @author Stefanie Drost<stefanie.drost@netresearch.de>
 */
class ExtensionFilter
{
    public function execute($extensionPath)
    {
        if (chdir($extensionPath))
        {
            if (chdir(''))
            {
                
            }
        }
    }
}

?>
