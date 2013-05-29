<?php
namespace MageCompatibility;

use MageCompatibility\Extension\Config;
use Netresearch\Logger;

class Extension extends Config
{
    protected $_extensionPath;

    protected $_usedClasses;
    protected $_usedMethods;

    protected $_databaseChanges;
    protected $_tables;

    protected $_phpMethods;

    /** @var mixed $methods Array of methods defined in this extension */
    protected $_methods;

    public function __construct($extensionPath)
    {
        $this->_extensionPath = $extensionPath;
    }

    public function getUsedMagentoMethods()
    {
        $this->_usedMethods = new Methods();
        $this->_addMethods($this->_extensionPath);
        return $this->_usedMethods;
    }

    public function getUsedMagentoClasses()
    {
        $this->_usedClasses = new Klasses();

        $extendsToken = 'extends';
        $extendedClassesRegexp = '/^class .* extends ([a-zA-Z0-9_].*)\W/mU';
        $this->_addClassesByRegexp($extendsToken, $extendedClassesRegexp);

        $factoryTypes = array(
            'Block'         => 'Block',
            'Model'         => 'Model',
            'ResourceModel' => 'Model/Mysql4'
        );
        foreach ($factoryTypes as $factoryType=>$filePathPattern) {
            $factoryRegexp = '/Mage\W*::\W*get' . $factoryType . '\W*\(\W*["\'](.*)["|\']\"*\)/mU';
            $this->_addClassesByRegexp($factoryType, $factoryRegexp, $filePathPattern);
        }

        return $this->_usedClasses;
    }

    protected function _addClassesByRegexp($token, $regexp, $filePathPattern=null)
    {
        $command = 'grep -rEl "' . $token . '" ' . $this->_extensionPath . '/app';
        exec($command, $filesWithThatToken, $return);

        if (0 == count($filesWithThatToken)) {
            return;
        }

        foreach ($filesWithThatToken as $filePath) {
            if ($this->_isUnitTestFile($filePath)) {
                continue;
            }
            $content = file_get_contents($filePath);
            preg_match($regexp, $content, $detailedMatches);
            if (1 < count($detailedMatches)) {
                $class = new Klass($detailedMatches[1], str_replace('/', '_', $filePathPattern));
                if ($class->isExtensionClass($detailedMatches[1], $filePathPattern, $this->_extensionPath)) {
                    continue;
                }
                $this->_usedClasses->add($class);
            }
        }
    }

    protected function _isUnitTestFile($filePath)
    {
        $filePath = str_replace($this->_extensionPath, '', $filePath);
        return (0 < preg_match('~app/code/.*/.*/Test/~u', $filePath) || 0 < preg_match('~tests~u', $filePath));
    }

    protected function _addMethods($path)
    {
        $parser = new \PHPParser_Parser(new \PHPParser_Lexer);
        foreach (glob($path . '/*') as $item) {
            if (is_dir($item)) {
                $this->_addMethods($item);
            }
            if (is_file($item) && is_readable($item)) {

                if ($this->_isUnitTestFile($item)) {
                    continue;
                }
                /* we assume that there are only php files */
                if (substr($item, -6) == '.stmts.xml') {
                    unlink($item); continue;
                }
                $fileNameParts = explode('.', basename($item));
                $extension = end($fileNameParts);
                if (false === in_array($extension, array('php', 'phtml'))) {
                    continue;
                }
                try {
                    $stmts = $parser->parse(file_get_contents($item));
                    //echo $item . PHP_EOL;
                    $serializer = new \PHPParser_Serializer_XML;
                    $xml = $serializer->serialize($stmts);
                    if (@simplexml_load_string($xml) === false) {
                        continue;
                    }
                    else {
//                        file_put_contents($item . '.stmts.xml', var_export($xml, true));
                        $numberOfMethodCalls = $this->_collectMethodCalls(
                            $stmts,
                            simplexml_load_string($xml)
                        );
                    }
                    //echo PHP_EOL;
                } catch (\PHPParser_Error $e) {
                    // no valid php
                    continue;
                }
            }
        }
    }

    protected function _getResultType(\SimpleXMLElement $node, $debug=false)
    {
        $type = Method::TYPE_MIXED;
        if ($node->xpath('./node:Expr_StaticCall')) {
            $class = current($node->xpath(
                './node:Expr_StaticCall/subNode:class/node:Name/subNode:parts/scalar:array/scalar:string/text()'
            ));
            if ($class && current($class) == 'Mage') {
                $node = current($node->xpath('./node:Expr_StaticCall'));
                $method = current($node->xpath('./subNode:name/scalar:string/text()'));
                $firstArgument = current($node->xpath('./subNode:args/scalar:array/node:Arg/subNode:value'));
                if (false === $firstArgument || false == $firstArgument->xpath('./node:Scalar_String')) {
                    return $type;
                }
                $firstArgument = current($firstArgument->xpath('./node:Scalar_String/subNode:value/scalar:string/text()'));
                if (in_array($method, array('getModel', 'getSingleton'))) {
                    $type = $this->_getClassName('model', $firstArgument);
                } elseif ('getBlock' == $method) {
                    $type = $this->_getClassName('block', $firstArgument);
                } elseif ('helper' == $method) {
                    $type = $this->_getClassName('helper', $firstArgument);
                }
            } elseif ($class && current($class) == 'parent') {
                /* @TODO: get return type of parent method */
            }
        } elseif ($node->xpath('./node:Name')) {
            $type = current(current($node->xpath('./node:Name/subNode:parts/scalar:array/scalar:string/text()')));
            if ('parent' == $type) {
                return $this->_getParentClass($node);
            }
        } elseif ($node->xpath('./node:Expr_Variable')) {
            $type = $this->_getTypeOfVariable($node);
        } elseif ($node->xpath('./node:Scalar_String')) {
            $type = Method::TYPE_STRING;
        } elseif ($node->xpath('./node:Expr_New')) {
            $type = (string) current($node->xpath('./node:Expr_New/subNode:class/node:Name/subNode:parts/scalar:array/scalar:string/text()'));
        } elseif ($node->xpath('./node:Expr_MethodCall')) {
            $methodName = current($node->xpath('./node:Expr_MethodCall/subNode:name/scalar:string/text()'));
            if ('load' == $methodName) {
                $caller = (string) current($node->xpath('./node:Expr_MethodCall/subNode:var/node:Expr_Variable/subNode:name/scalar:string/text()'));
                $assignedVar = (string) current($node->xpath('./node:Expr_MethodCall/../../subNode:var/node:Expr_Variable/subNode:name/scalar:string/text()'));
                // avoid infinity loops due to recursion
                if ($caller != $assignedVar) {
                    $type = $this->_getResultType(current($node->xpath('./node:Expr_MethodCall/subNode:var')));
                }
            } elseif ('get' === substr($methodName, 0, 3) && 'Id' === substr($methodName, -2)) {
                $type = Method::TYPE_INT;
            }
        }

        return $type;
    }

    protected function _getParentClass($node)
    {
        $extends = $node->xpath('./ancestor::node:Stmt_Class/subNode:extends/node:Name/subNode:parts/scalar:array/scalar:string/text()');
        if ($extends && 0 < count($extends)) {
            return current(current($extends));
        }
        throw new \Exception('Extension uses parent without extending another class');
    }

    /**
     * determine type of a variable
     *
     * @param SimpleXMLElement $node
     * @return string
     */
    protected function _getTypeOfVariable($node)
    {
        $type = Method::TYPE_MIXED;
        $variableName = current($node->xpath('./node:Expr_Variable/subNode:name/scalar:string/text()'));
        if ('this' == $variableName) {
            /* @TODO: $this may refer to parent or child class if method is not defined here */
            $className = current($node->xpath('./ancestor::node:Stmt_Class/subNode:name/scalar:string/text()'));
            return (false == $className) ? $type : current($className);
        }
        $usedInLine = (int) current($node->xpath('./node:Expr_Variable/attribute:endLine/scalar:int/text()'));
        $methodXpath = './ancestor::node:Stmt_ClassMethod';
        $currentMethod = current($node->xpath($methodXpath));
        if (false === $currentMethod) {
            return $type;
        }

        $definedInLine = 0;
        $lastAssignment = $this->_getLastAssignment($currentMethod, $variableName, $usedInLine);
        if (is_array($lastAssignment)) {
            $definedInLine = key($lastAssignment);
            $type = current($lastAssignment);
        }

        /* if variable is method parameter with type hint */
        $isParamXpath = sprintf(
            './ancestor::node:Stmt_ClassMethod/subNode:params/scalar:array/node:Param[subNode:name/scalar:string/text() = "%s"]/subNode:type/node:Name/subNode:parts/scalar:array/scalar:string/text()',
            $variableName
        );
        $paramTypes = $node->xpath($isParamXpath);
        if ($paramTypes) {
            $type = current($paramTypes);
            if (false !== $type && false == is_string($type)) {
                $type = current($type);
            }
        }
        return $type;
    }

    /**
     * get last assignment to that variable
     *
     * @param SimpleXMLElement $method
     * @param string           $variableName
     * @return array(line => type) || NULL
     */
    protected function _getLastAssignment(\SimpleXMLElement $method, $variableName, $usedInLine)
    {
        $variableDefinitionXpath = sprintf(
            './descendant::node:Expr_Assign[subNode:var/node:Expr_Variable/subNode:name/scalar:string/text() = "%s"]',
            $variableName
        );
        $variableDefinitions = $method->xpath($variableDefinitionXpath);
        $lastAssignmentLine = 0;
        $lastAssignment = null;
        foreach ($variableDefinitions as $key=>$assignment) {
            $assignmentLine = (int) current($assignment->xpath('./attribute:endLine/scalar:int/text()'));
            if ($usedInLine < $assignmentLine) {
                continue;
            }
            if ($lastAssignmentLine <= $assignmentLine) {
                $lastAssignmentLine = $assignmentLine;
                $lastAssignment = $assignment;
            }
        }
        if (false == is_null($lastAssignment)) {
            return array($lastAssignmentLine => $this->_getResultType(current($lastAssignment->xpath('./subNode:expr'))));
        }
    }

    protected function _getClassName($type, $identifier)
    {
        $className = Method::TYPE_MIXED;
        $configFiles = glob($this->_extensionPath . '/app/code/*/*/*/etc/config.xml');
        foreach ($configFiles as $configFile) {
            $extensionConfig = simplexml_load_file($configFile);
            if (false !== strpos($identifier, '/')) {
                list($module, $path) = explode('/', $identifier);
            } else {
                $module = $identifier;
                $path = 'data';
            }
            $xpath = '/config/*/' . $type . 's/' . $module . '/class/text()';
            $identifierPathParts = explode('_', $path);
            $className = current($extensionConfig->xpath($xpath));
            if (false !== $className && false == is_string($className)) {
                $className = current($className);
            }
            if (false == $className) {
                $className = 'Mage_' . ucfirst($module) . '_' . ucfirst($type);
            }
            foreach ($identifierPathParts as $part) {
                $className .= '_' . ucfirst($part);
            }
        }
        return $className;
    }

    /**
     * collect method calls
     *
     * @param PHPParser_Node_Stmt $stmt
     * @return int Number of called methods
     */
    protected function _collectMethodCalls($stmt, $xmlTree)
    {
        $numberOfMethodCalls = 0;
        $methodCallXPath = '//node:Expr_MethodCall | //node:Expr_StaticCall';
        $methodCalls = $xmlTree->xpath($methodCallXPath);
        foreach ($methodCalls as $call) {
            $methodName = $call->xpath('./subNode:name/scalar:string/text()');
            if (false === $methodName) {
                continue;
            }
            $methodName = current($methodName);
            if (is_array($methodName)) {
                $methodName = current($methodName);
            }

            $variable = current($call->xpath('./subNode:var | ./subNode:class'));
            $object = $this->_getResultType($variable);
            if (false == $object) {
                continue;
            }

            if ($this->_isPhpMethod($object, $methodName)) {
                continue;
            }

            if ($this->_isCallabilityChecked($call, $object, $methodName)) {
                Logger::addComment(
                    $this->_extensionPath,
                    'MageCompatibility',
                    sprintf('<info>Found version switch</info> for %s::%s', $object, $methodName)
                );
                continue;
            }

            if (false == $this->_isExtensionMethod($object, $methodName)) {
                $method = new Method(
                    (string) $methodName,
                    $this->_getArgs($call),
                    array('class' => $object)
                );
                ++$numberOfMethodCalls;
                $this->_usedMethods->add($method);
            }
        }
        return $numberOfMethodCalls;
    }

    /**
     * determine parameter types
     *
     * @param SimpleXMLElement $call Method call
     * @return array
     */
    protected function _getArgs(\SimpleXMLElement $call)
    {
        $args = $call->xpath('./subNode:args/scalar:array/node:Arg/subNode:value');
        foreach ($args as $pos=>$arg) {
            $args[$pos] = $this->_getResultType($arg, true);
        }

        return $args;
    }

    protected function _isPhpMethod($class, $method)
    {
        $method = strtolower((string) $method);
        if (is_null($this->_phpMethods)) {
            $this->_phpMethods = array();
            exec('php -r "echo implode(PHP_EOL, get_declared_classes());"', $output);
            foreach ($output as $definedClass) {
                $this->_phpMethods[$definedClass] = get_class_methods($definedClass);
            }
        }
        if (array_key_exists($class, $this->_phpMethods)) {
            foreach ($this->_phpMethods[$class] as $phpMethod) {
                if ($method == strtolower($phpMethod)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * if existance of the method is checked before call
     *
     * @param SimpleXMLElement $call PHPParser_Parser xml
     * @param string $variable       class name or variable with that object
     * @param string $methodName     method name
     * @return void
     */
    protected function _isCallabilityChecked($call, $variable, $methodName)
    {
        $xpath = sprintf('./ancestor::node:Stmt_If/subNode:cond/node:Expr_FuncCall[subNode:name/node:Name/subNode:parts/scalar:array/scalar:string/text() = "is_callable"]/subNode:args/scalar:array/node:Arg/subNode:value/node:Scalar_String/subNode:value/scalar:string[text() = "%s"]', "$variable::$methodName");
        return (count($call->xpath($xpath)));
    }

    /**
     * if given method is part of the extension
     *
     * @param string $className
     * @param string $methodName
     * @return boolean
     */
    protected function _isExtensionMethod($className, $methodName)
    {
        $classPath = current(glob($this->_extensionPath . '/app/code/*/' . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php'));
        if (file_exists($classPath)) {
            /*if ($this->isExtensionDatabaseAccessor($className, $methodName)) {
                return true;
            }*/
            $command = sprintf('grep -i "function %s" %s', $methodName, $classPath);
            exec($command, $matches, $notFound);
            if (0 < count($matches)) {
                return true;
            }
        }
        return false;
    }

    /**
     * if given method is a database field accessor related to a table that is defined by the extension
     *
     * @param string $className
     * @param string $methodName
     * @return boolean
     */
    protected function _isExtensionDatabaseAccessor($className, $methodName)
    {
        if (3 < strlen($methodName) && in_array(substr($methodName, 0, 3), array('get', 'set', 'uns', 'has'))) {
            $fieldName = $this->_getFieldNameForAccessor($methodName);
            $changes = $this->_getDatabaseChanges();
            if (false === array_key_exists('add', $changes)) {
                return false;
            }
            $additionalProperties = $changes['add'];
            if (0 == count($additionalProperties)) {
                return false;
            }
            $tableName = null;
            foreach ($additionalProperties as $table=>$fields) {
                if (false == in_array($fieldName, $fields)) {
                    continue;
                }
                $tableName = $table;
                break;
            }
            if (false == is_null($tableName) && $this->_getTableForClass($className) === $tableName) {
                return true;
            }
        }
        return false;
    }

    protected function _getTableForClass($className)
    {
        if (is_null($this->_tables)) {
            $this->_tables = $this->_getTables($this->_extensionPath);
        }
        if (array_key_exists($className, $this->_tables)) {
            return $this->_tables[$className];
        }
    }

    protected function _getFieldNameForAccessor($methodName)
    {
        return strtolower(implode('_', preg_split('/(?<=\\w)(?=[A-Z])/', substr($methodName, 3))));
    }

    /**
     * get an array of methods associated to the file they are defined in
     *
     * @return array
     */
    public function getMethods()
    {
        if (is_null($this->_methods)) {
            $this->_methods = array();
            $command = sprintf( 'grep -oriE " function ([a-zA-Z0-9_]*)" %s', $this->_extensionPath . '/app/code/');
            exec($command, $output);
            foreach ($output as $line) {
                if (false === strpos($line, ':')) {
                    continue;
                }
                list($path, $method) = explode(':', $line);
                $this->_methods[trim(str_replace('function', '', $method))] = trim(substr_replace($this->_extensionPath, '', $path));
            }
        }
        return $this->_methods;
    }

    /**
     * if extension has a method with the given name
     *
     * @param string $methodName
     * @return boolean
     */
    public function hasMethod($methodName)
    {
        return array_key_exists($methodName, $this->getMethods());
    }

    /**
     * determine database changes made in sql install and/or update scripts
     */
    protected function _getDatabaseChanges()
    {
        if (is_null($this->_databaseChanges)) {
            $this->_databaseChanges = array();
            $scripts = glob($this->_extensionPath . '/app/code/*/*/*/sql/*/mysql*');
            foreach ($scripts as $script) {
                $setup = new Extension\Setup($script);
                $this->_databaseChanges = array_merge_recursive($this->_databaseChanges, $setup->getChanges());
            }
        }
        return $this->_databaseChanges;
    }
}
