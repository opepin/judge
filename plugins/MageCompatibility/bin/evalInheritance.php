<?php
$basedir = realpath(dirname(__FILE__) . '/../../../');
require_once $basedir . '/vendor/dg/dibi/dibi/dibi.php';
dibi::connect(array(
    //'driver'   => 'sqlite3',
    //'database' => $basedir . '/plugins/MageCompatibility/var/tags.sqlite'
    'driver'   => 'mysql',
    'username' => 'root',
    'database' => 'judge'
));

$evaluator = new InheritanceEvaluator();
$evaluator->setBaseDir($basedir);
$evaluator->run();

class InheritanceEvaluator
{
    protected $_classes = array();
    protected $_parents = array();
    protected $_magento = array();
    protected $_baseDir;

    public function setBaseDir($baseDir)
    {
        $this->_baseDir = $baseDir;
    }

    public function run()
    {
        if (false == file_exists($this->_baseDir . '/graphs')) {
            mkdir($this->_baseDir . '/graphs');
        }
        $dotfiles = array();
        $query = '
            SELECT
                t.id as classId,
                t.name as className,
                m.id as magentoId,
                IF(LOCATE("extends", definition), TRIM(REPLACE(SUBSTR(definition, LOCATE("extends", definition)+8), "{", "")), NULL) as parentClassName,
                s.definition,
                CONCAT_WS("-", m.edition, m.version) as mage
            FROM [classes] t
                JOIN [class_signature] ts ON ( t.id = ts.class_id )
                JOIN [signatures] s ON ( ts.signature_id = s.id)
                JOIN [magento_signature] ms ON ( s.id = ms.signature_id)
                JOIN [magento] m ON ( m.id = ms.magento_id)
            ';
        $classInheritance = dibi::fetchAll($query);

        foreach ($classInheritance as $inheritance) {
            $childName  = $inheritance->className;
            $parentName = $inheritance->parentClassName;
            $mage       = $inheritance->mage;

            if (false === array_key_exists($inheritance->magentoId, $this->_magento)) {
                $this->_classes[$inheritance->magentoId] = $mage;
            }
            if (false === array_key_exists($mage, $this->_classes)) {
                $this->_classes[$mage] = array();
            }
            if (false === array_key_exists($mage, $this->_parents)) {
                $this->_parents[$mage] = array();
            }
            if (false === array_key_exists($childName, $this->_classes)) {
                $this->_classes[$mage][$childName] = new Klass($childName);
            }
            $this->_classes[$mage][$childName]->setId($inheritance->classId);
            $this->_classes[$mage][$childName]->setMagentoId($inheritance->magentoId);
            $this->_classes[$mage][$childName]->setDefinition($inheritance->definition);

            /* remove child from main parent array, if a class inherits from another one */
            if (array_key_exists($childName, $this->_parents)) {
                unset($this->_parents[$childName]);
            }

            $parentName = trim(preg_replace('/implements.*$/s', '', $parentName));
            $parentName = trim(preg_replace('/[^A-Za-z0-9_]/', '', $parentName));
            if (0 < strlen($parentName)) {

                $dotfile = $this->_baseDir . '/graphs/inheritance_' . $inheritance->mage . '.dot';
                if (false == file_exists($dotfile)) {
                    file_put_contents($dotfile, 'digraph G {' . PHP_EOL);
                    $dotfiles[] = $dotfile;
                }
                if (false === array_key_exists($parentName, $this->_classes[$mage])) {
                    $this->_classes[$mage][$parentName] = new Klass($parentName);
                    $this->_classes[$mage][$parentName]->setDefinition($inheritance->parentClassName);
                    $this->_parents[$mage][$parentName] = $this->_classes[$mage][$parentName];
                }
                file_put_contents($dotfile, $parentName . ' -> ' . $childName . ';' . PHP_EOL, FILE_APPEND);
                $parentClass = $this->_classes[$mage][$parentName];
                $childClass  = $this->_classes[$mage][$childName];
                $parentClass->addChild($childClass);
            }
        }
        foreach ($dotfiles as $dotfile) {
            file_put_contents($dotfile, '}', FILE_APPEND);
        }
        foreach($this->_parents as $mage=>$parents) {
            foreach($parents as $parent) {
                $this->_saveInheritedMethods($parent);
            }
        }
    }

    protected function _saveInheritedMethods($class, $parentMethods=array())
    {
        $methods = array();
        if (false == $this->_isBuiltinClass($class->getName())) {
            if (is_null($class->getId())) {
                echo "Found {$class->getName()} to be parent class for {$class->getChildrenCount()} classes, but it does not exist at all!" . php_eol;
                foreach ($class->getchildren() as $child) {
                    echo "* {$child->getName()} in magento {$this->_magento[$class->getmagentoid()]}" . PHP_EOL;
                }
                return;
            }

            $methods = dibi::query('
                SELECT s.id as signature_id, t.name as method
                FROM [methods] t
                JOIN [method_signature] ts ON (t.id = ts.method_id)
                JOIN [signatures] s ON (s.id = ts.signature_id)
                JOIN [magento_signature] ms ON (s.id = ms.signature_id)
                WHERE ms.magento_id = ? AND t.class_id = ? AND s.definition NOT LIKE "%private function%"
                ',
                $class->getMagentoId(),
                $class->getId()
            )->fetchPairs();
        } else {
            echo "Skip builtin class {$class->getName()}" . PHP_EOL;
        }

        foreach ($class->getChildren() as $child) {
            foreach ($methods as $signatureId=>$methodName) {
                dibi::query(
                    'INSERT INTO [flat_method_inheritance] SET class_id = ?, signature_id = ?, magento_id = ?',
                    $child->getId(),
                    $signatureId,
                    $class->getMagentoId()
                );
            }
            $this->_saveInheritedMethods($child, array_merge($methods, $parentMethods));
        }
    }

    protected function _isBuiltinClass($name)
    {
        $builtinClasses = array(
            'ArrayObject',
            'Countable',
            'Exception',
            'FilterIterator',
            'IteratorCountable',
            'LimitIterator',
            'RecursiveFilterIterator',
            'RecursiveIterator',
            'ReflectionClass',
            'ReflectionExtension',
            'ReflectionMethod',
            'ReflectionFunction',
            'ReflectionParameter',
            'ReflectionProperty',
            'Serializable',
            'SimpleXMLElement',
            'SoapClient',
            'SplFileObject',
        );
        if (in_array($name, $builtinClasses)) {
            return true;
        }
        if (0 === strpos($name, 'PHPUnit_')) {
            return true;
        }
        return false;
    }
}

class Klass
{
    protected $_name;
    protected $_definition;
    protected $_id;
    protected $_magentoId;
    protected $_children=array();

    public function __construct($name)
    {
        $this->_name = $name;
    }

    public function getName()
    {
        return $this->_name;
    }

    public function addChild(Klass $child)
    {
        $this->_children[$child->getName()] = $child;
    }

    public function setId($id)
    {
        $this->_id = $id;
    }

    public function setDefinition($definition)
    {
        $this->_definition = $definition;
    }

    public function getId()
    {
        return $this->_id;
    }

    public function setMagentoId($magentoId)
    {
        $this->_magentoId = $magentoId;
    }

    public function getMagentoId()
    {
        return $this->_magentoId;
    }

    public function getChildren()
    {
        return $this->_children;
    }

    public function getChildrenCount()
    {
        $count = count($this->_children);
        foreach ($this->_children as $child) {
            $count += $child->getChildrenCount();
        }
        return $count;
    }
}
