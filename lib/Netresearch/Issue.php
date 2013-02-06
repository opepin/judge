<?php
namespace Netresearch;

class Issue 
{
//    public $type;
//    public $failed;
//    public $checked;
//    public $checkName;
//    public $filename;
//    public $linenumber;
//    public $comment;
    
    public $data = array();
     
    function __construct($dataArray) 
    {
        $this->data = $dataArray;
        return $this;
    }
    
    /*public function setType($type)
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
    
    public function getComment()
    {
        return $this->comment;
    }*/
    
    public function __set($name, $value)
    {
        $this->data[$name] = $value;
        return $this;
    }

    public function __get($name)
    {
        if (array_key_exists($name, $this->data))
        {
            return $this->data[$name];
        }
        return null;
    }
    
    function __call($method, $arguments)
    {
        if(preg_match("/get/i", $method))
        {
        $property = $this->from_camel_case(substr($method, 3, strlen($method) - 3));
        return $this->$property;
        
        
        } else if (preg_match("/set/i", $method))
        {
            $property = $this->from_camel_case(substr($method, 3, strlen($method) - 3));
            $value = '';
            
            if(count($arguments) >= 1)
                $value = $arguments[0];
           
            $this->$property = $value;
            return $this;      
        }
    }

    function from_camel_case($str)
    {
        $str[0] = strtolower($str[0]);
        $func = create_function('$c', 'return "_" . strtolower($c[1]);');
        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

}