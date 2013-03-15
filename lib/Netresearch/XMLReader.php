<?php
namespace Netresearch;

/**
 * Description of XMLReader
 *
 * @author Stefanie Drost<stefanie.drost@netresearch.de>
 */
class XMLReader
{
    protected static $vendor;
    protected static $vendorNode;
    protected static $extensionName;
    protected static $version;
    
    
    public static function getVendor()
    {
        return self::$vendor;
    }
    
    public static function getExtensionName()
    {
        return self::$extensionName;
    }
    
    public static function getVersion()
    {
        return self::$version;
    }
    
    
    public static function readConfig($extensionPath)
    {
        // search config.xml file
        $files = self::searchConfig($extensionPath);
        
        if( count($files) > 0) {
            $xml = simplexml_load_file($files[0]); 
        }
        
        $i = 0;
        foreach($xml->modules->children() as $child) {
            $i++;
            if($i == 1) {
                self::$vendorNode = $child;
                
                $vendorAndModule = $child->getName();
                $parts = explode('_', $vendorAndModule);
                
                self::$vendor = $parts[0];
                self::$extensionName = $parts[1];
            }
            else {
                break;
            }            
        }
        self::$version = (string)self::$vendorNode->version;
    }
    
    public static function searchConfig($extensionPath)
    {
        $searchedFile = 'config.xml';
        $filelist = array();
        $dir = dir($extensionPath);

        while (false !== ($file = $dir->read())) {

            if (('.' == $file) or ('..' == $file) or !is_readable(self::includeTrailingPathDelimiter($extensionPath) . $file))
                continue;

            if (is_dir(self::includeTrailingPathDelimiter($extensionPath) . $file)) {
                $filelist = array_merge($filelist, self::searchConfig(self::includeTrailingPathDelimiter($extensionPath) . $file, $searchedFile));
            } else {
                if (preg_match("/$searchedFile/", $file)) {
                    array_push($filelist, self::includeTrailingPathDelimiter($extensionPath) . $file);
                }
            }
        }

        $dir->close();

        return $filelist;
    }
    
    protected static function includeTrailingPathDelimiter($path, $backslash = false)
    {
        if (!self::hasTrailingPathDelimiter($path)) {
            if ($backslash) {
                return $path . '\\';
            } else {
                return $path . '/';
            }
        } else {
            return $path;
        }
    }

    protected static function hasTrailingPathDelimiter($path)
    {
        return ($path[strlen($path) - 1] == '/') or ($path[strlen($path) - 1] == '\\');
    }
}

?>
