<?php
namespace MageCompatibility;

class Tags implements \Iterator
{
    protected $_position = 0;
    protected $_ata = array();

    public function count()
    {
        return count($this->_ata);
    }

    public function current()
    {
        return $this->_ata[$this->_position];
    }

    public function rewind()
    {
        $this->_position = 0;
    }

    public function key()
    {
        return $this->_position;
    }
        
    function next()
    {
        ++$this->_position;
    }

    function valid()
    {
        return isset($this->_ata[$this->_position]);
    }
}
