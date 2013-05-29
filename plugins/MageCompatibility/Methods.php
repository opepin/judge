<?php
namespace MageCompatibility;

class Methods extends Tags
{
    protected $_ata = array();

    public function add(Method $method)
    {
        $this->_ata[] = $method;
    }

    public function count()
    {
        return count($this->_ata);
    }
}
