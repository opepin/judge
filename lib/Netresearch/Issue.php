<?php
namespace Netresearch;

class Issue 
{
    public static $type;
    public static $failed;
    public static $checked;
    public static $checkName;
    public static $filename;
    public static $linenumber;
    public static $comment;
    
    function __construct() 
    {
        return $this;
    }
    
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }
    
    public function setFailed($failed)
    {
        $this->failed = $failed;
        return $this;
    }
    
    public function setChecked($checked)
    {
        $this->checked = $checked;
        return $this;
    }
    
    public function setCheckName($checkName)
    {
        $this->checkName = $checkName;
        return $this;
    }
    
    public function setFilename($filename)
    {
        $this->filename = $filename;
        return $this;
    }
    
    public function setLinenumber($linenumber)
    {
        $this->linenumber = $linenumber;
        return $this;
    }
    
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }
    
    public function getType()
    {
        return $this->type;
    }
    
    public function getFilename()
    {
        return $this->filename;
    }
    
    public function getLinenumber()
    {
        return $this->linenumber;
    }
    
    public function getCheckName()
    {
        return $this->checkName;
    }

}
