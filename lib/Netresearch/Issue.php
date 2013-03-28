<?php
namespace Netresearch;

class Issue
{
    protected $extension;
    protected $checkname;
    protected $type;
    protected $failed;
    protected $comment;
    protected $linenumber;
    protected $files = array();
    
//    public $data = array();
//    protected $allowedAttributes = array("checkname", "type", "comment");
     
    function __construct($dataArray = null) 
    {
        foreach ($dataArray as $key => $value)
        {
            $method = 'set' . ucfirst($key);
            $this->$method($value);
        }
        return $this;
    }
    
    public function setExtension($extension)
    {
        $this->extension = $extension;
        return $this;
    }
    
    public function setCheckname($checkname)
    {
        $this->checkname = $checkname;
        return $this;
    }
    
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }
    
    public function setComment($comment)
    {
        $this->comment = ltrim(str_replace($this->extension, '', $comment), DIRECTORY_SEPARATOR);
        return $this;
    }
    
    public function setFiles($files)
    {
        if (!empty($files)) {
            foreach ($files as $key => $file) {
                $files[$key] = ltrim(str_replace($this->extension, '', $file), DIRECTORY_SEPARATOR);
            }
        }
        $this->files = $files;
        return $this;
    }
    
    public function setLinenumber($linenumber)
    {
        $this->linenumber = $linenumber;
        return $this;
    }
    
    public function setFailed($failed)
    {
        $this->failed = $failed;
        return $this;
    }
    
    public function getExtension()
    {
        return $this->extension;
    }
    
    public function getCheckname()
    {
        return $this->checkname;
    }
    
    public function getType()
    {
        return $this->type;
    }
    
    public function getComment()
    {
        return $this->comment;
    }
    
    public function getFiles()
    {
        return $this->files;
    }
    
    public function getLinenumber()
    {
        return $this->linenumber;
    }
    
    public function getFailed()
    {
        return $this->failed;
    }
    
    public function getJsonData()
    {
        $data = get_object_vars($this);
        return json_encode($data);
    }

}