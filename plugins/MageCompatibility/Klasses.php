<?php
namespace MageCompatibility;

class Klasses extends Tags
{
    protected $_position = 0;
    protected $_ata = array();

    /**
     * add a class name
     * 
     * @param string $name Name of the class
     * @return Klasses
     */
    public function add(Klass $class)
    {
        $this->_ata[] = $class;
        return $this;
    }

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
        
    function next() {
        ++$this->_position;
    }

    function valid() {
        return isset($this->_ata[$this->_position]);
    }
}
