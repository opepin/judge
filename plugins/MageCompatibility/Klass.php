<?php
namespace MageCompatibility;

class Klass extends Tag
{
    const TYPE_UNKNOWN   = '-1';
    const TAG_TYPE       = 'c';
    const TABLE          = 'methods';
    const RELATION_TABLE = 'method_signature';

    protected $_shortTagType = 'c';
    protected $_tagType      = 'class';
    protected $_table        = 'classes';

    protected $_name;

    protected $_type=self::TYPE_UNKNOWN;

    /**
     * create Klass with given name
     * 
     * @param string $name 
     * @return Klass
     */
    public function __construct($name, $type=null)
    {
        $this->setName($name);
        if (false == empty($type)) {
            $this->setType($type);
        }
    }

    /**
     * set class name
     * 
     * @param string $name 
     * @return Klass
     */
    public function setName($name)
    {
        $this->_name = $name;
        return $this;
    }

    public function setType($type)
    {
        $this->_type = $type;
    }

    public function getType()
    {
        return self::TYPE_UNKNOWN == $this->_type ? null : $this->_type;
    }

    public function getName()
    {
        return $this->_getMagentoClassName($this->_name, $this->_type);
    }

    public function isExtensionClass($identifier, $filePathPattern, $extensionPath)
    {
        if (0 < preg_match('/^([a-zA-Z0-9]+_)+[a-zA-Z0-9]+$/', $identifier)) {
            /* we got a class name */
            $className = $identifier;
            $token = 'class ' . $className;
            $command = 'grep -rEl "' . $token . '" ' . $extensionPath . '/app';
            exec($command, $filesWithThatToken, $return);
        } else {
            $filePathPattern = 'app/code/*/*/*/' . $filePathPattern;

            list($extensionName, $classPathItems) = $this->_getIdentifierParts($identifier);
            foreach ($classPathItems as $pathItem) {
                $filePathPattern .= '/' . ucfirst($pathItem);
            }
            $filePathPattern .= '.php';
            $files = glob($extensionPath . '/' . $filePathPattern);
            return (0 < count($files));
        }
    }

    protected function _getMagentoClassName($identifier, $type)
    {
        if (0 < preg_match('/^([a-zA-Z0-9]+_)+[a-zA-Z0-9]+$/', $identifier)) {
            return $identifier;
        }
        list($extensionName, $classPathItems) = $this->_getIdentifierParts($identifier);
        $className = 'Mage_' . ucfirst($extensionName) . '_' . ucfirst($type);

        foreach ($classPathItems as $pathItem) {
            $className .= '_' . ucfirst($pathItem);
        }
        return $className;
    }

    protected function _getIdentifierParts($identifier)
    {
        $identifierParts = explode('/', $identifier);
        $class = 'data';
        $extensionName = $identifierParts[0];
        if (1 < count($identifierParts)) {
            $class = $identifierParts[1];
        }
        $classPathItems = explode('_', $class);
        return array($extensionName, $classPathItems);
    }
}

