<?php
namespace MageCompatibility;

class Klasses extends Tags
{
    protected $position = 0;
    protected $data = array();

    /**
     * add a class name
     * 
     * @param string $name Name of the class
     * @return Klasses
     */
    public function add(Klass $class)
    {
        $this->data[] = $class;
        return $this;
    }

    public function count()
    {
        return count($this->data);
    }

    public function current()
    {
        return $this->data[$this->position];
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function key()
    {
        return $this->position;
    }
        
    function next() {
        ++$this->position;
    }

    function valid() {
        return isset($this->data[$this->position]);
    }
}
