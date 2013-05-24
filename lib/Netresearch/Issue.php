<?php
namespace Netresearch;

class Issue
{
    protected $_extension;
    protected $_checkname;
    protected $_type;
    protected $_failed;
    protected $_comment;
    protected $_linenumber;
    protected $_files = array();
    
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
        $this->_extension = $extension;
        return $this;
    }
    
    public function setCheckname($checkname)
    {
        $this->_checkname = $checkname;
        return $this;
    }
    
    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }
    
    public function setComment($comment)
    {
        $this->_comment = ltrim(str_replace($this->_extension, '', $comment), DIRECTORY_SEPARATOR);
        return $this;
    }
    
    public function setFiles($files)
    {
        if (!empty($files)) {
            foreach ($files as $key => $file) {
                $files[$key] = ltrim(str_replace($this->_extension, '', $file), DIRECTORY_SEPARATOR);
            }
        }
        $this->_files = $files;
        return $this;
    }
    
    public function setLinenumber($linenumber)
    {
        $this->_linenumber = $linenumber;
        return $this;
    }
    
    public function setFailed($failed)
    {
        $this->_failed = $failed;
        return $this;
    }
    
    public function getExtension()
    {
        return $this->_extension;
    }
    
    public function getCheckname()
    {
        return $this->_checkname;
    }
    
    public function getType()
    {
        return $this->_type;
    }
    
    public function getComment()
    {
        return $this->_comment;
    }
    
    public function getFiles()
    {
        return $this->_files;
    }
    
    public function getLinenumber()
    {
        return $this->_linenumber;
    }
    
    public function getFailed()
    {
        return $this->_failed;
    }
    
    public function getJsonData()
    {
        $data = get_object_vars($this);
        return json_encode($data);
    }

}