<?php
namespace MageCompatibility;

use MageCompatibility\Extension\Config;

include realpath(dirname(__FILE__) . '/../Extension/Config.php');

if (count($_SERVER['argv']) < 3) {
    die('Call with ' . __FILE__ . ' {path to magento} {database name}' . PHP_EOL);
}
$branch = $_SERVER['argv'][1];
$databaseName = $_SERVER['argv'][2];

class DatabaseParser extends Config
{
    protected $_pathToMagentoBaseDir;
    protected $_databaseName;
    protected $_edition;
    protected $_version;
    protected $_basedir;
    protected $_tagFile;
    protected $_resourceModelNames;
    protected $_classIdentifiers;

    public function __construct($branch, $databaseName)
    {
        $this->_basedir = realpath(dirname(__FILE__) . '/../../../');
        require_once $this->_basedir . '/vendor/dg/dibi/dibi/dibi.php';

        $this->_databaseName = $databaseName;

        $this->_createJumpstormIni($branch);
        $this->_setUpEnv();
        $this->_verifyMagento($this->_pathToMagentoBaseDir);

        \dibi::connect(array(
            //'driver'   => 'sqlite3',
            //'database' => $basedir . '/plugins/MageCompatibility/var/tags.sqlite'
            'driver'   => 'mysql',
            'username' => 'root',
            'database' => $this->_databaseName
        ));
        file_put_contents($this->_getTagFileName(), '');
    }

    protected function _verifyMagento($pathToMagentoBaseDir)
    {
        include $pathToMagentoBaseDir . 'app/Mage.php';

        $this->_version = \Mage::getVersion();
        if (method_exists('Mage', 'getEdition')) {
            $this->_edition = \Mage::getEdition();
        } else {
            preg_match('/^1\.(\d+)\./', $this->_version, $matches);
            $majorRelease = $matches[1];
            $this->_edition = ($majorRelease < 7) ? 'Community' : 'Enterprise';
        }
        echo 'Analyzing Magento ' . $this->_version . ' (' . $this->_edition . ' Edition)...' . PHP_EOL;
    }

    protected function _createJumpstormIni($branch)
    {
        $config = file_get_contents($this->_basedir . '/plugins/MageCompatibility/var/base.jumpstorm.ini');
        $this->jumpstormConfigFile = $this->_basedir . '/plugins/MageCompatibility/var/tmp.jumpstorm.ini';
        $config = str_replace('###branch###', $branch, $config);
        $config = str_replace('###target###', $this->_basedir . '/tmp/' . $branch, $config);
        $this->_pathToMagentoBaseDir = $this->_basedir . '/tmp/' . $branch . '/';
        $config = str_replace('###database###', $this->_databaseName, $config);
        file_put_contents($this->jumpstormConfigFile, $config);
    }

    protected function _getTagFileName()
    {
        return $this->_basedir . '/plugins/MageCompatibility/var/tags/'
            . strtolower($this->_edition) . 'Database-' . $this->_version . '.tags';
    }

    public function run()
    {
        $tables = $this->_getTables($this->_pathToMagentoBaseDir);
        foreach ($tables as $class=>$tableName) {
            $this->_writeMethodsForFlatTable($class, $tableName);
        }

        $eavEntities = $this->_getEavEntities(array_keys($tables));
        foreach ($eavEntities as $class) {
            $this->_writeMethodsForEavAttributes($class, $tables[$class]);
        }
    }

    protected function _writeMethodsForFlatTable($class, $tableName)
    {
        try {
            $fields = \dibi::query('DESCRIBE [' . $tableName . ']');
            $this->_writeMethodsForFields($class, $tableName, $fields, 'flat');
        } catch (\Exception $e) {
            // skip non-existing tables
        }
    }

    protected function _writeMethodsForEavAttributes($model, $table)
    {
        $query = 'SELECT attribute_code as Field
            FROM eav_attribute a
            JOIN eav_entity_type t ON t.entity_type_id = a.entity_type_id
            WHERE entity_model = %s';
        $this->_writeMethodsForFields($model, $table, \dibi::query($query, $this->_classIdentifiers[$model]), 'eav');
    }

    protected function _writeMethodsForFields($class, $tableName, $fields, $type)
    {
        $lines = array();
        foreach ($fields as $row) {
            $lines[] = $this->_getTaglineForField($class, $tableName, $row->Field, $type, 'get', '$value=null');
            $lines[] = $this->_getTaglineForField($class, $tableName, $row->Field, $type, 'set', '$value=null');
            $lines[] = $this->_getTaglineForField($class, $tableName, $row->Field, $type, 'uns', '$value=null');
            $lines[] = $this->_getTaglineForField($class, $tableName, $row->Field, $type, 'has', '$value=null');
        }
        file_put_contents($this->_getTagFileName(), $lines, FILE_APPEND);
    }

    protected function _getTaglineForField($class, $tableName, $fieldName, $type, $prefix, $params='')
    {
        $camelCaseFieldName = str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));
        $methodName = $prefix . $camelCaseFieldName;
        $method = array(
            'name'     => $methodName,
            'path'     => "database[$class/$fieldName/$type/$tableName]",
            'codeLine' => "/^public function $methodName($params)$/;\"",
            'type'     => 'f',
            'line'     => 'line:[magic]'
        );
        return implode("\t", $method) . PHP_EOL;
    }

    protected function _setUpEnv()
    {
        echo 'Setting Up Magento environment via jumpström' . PHP_EOL;
        $iniFile = $this->jumpstormConfigFile;
        $installMagentoCommand = 'magento -v -c ' . $iniFile;
        $executable = $this->_basedir . '/vendor/netresearch/jumpstorm/jumpstorm';
        passthru(sprintf('%s %s', $executable, $installMagentoCommand), $error);
        if ($error) {
            die('Installation failed!');
        }
    }

    protected function _getEavEntities($models)
    {
        $eavModels = array();
        foreach ($models as $model) {
            if (false === array_key_exists($model, $this->_resourceModelNames)) {
                continue;
            }
            $resourceModel = $this->_resourceModelNames[$model];
            if ($this->_isEavModel($resourceModel)) {
                echo "* EAV: $model\n";
                $eavModels[] = $model;
            }
        }
        return $eavModels;
    }

    protected function _isEavModel($model) {
        $command = sprintf('grep -rEzoh "%s extends \w+" %s/app/code/core/', $model, $this->_pathToMagentoBaseDir);
        exec($command, $output, $noMatch);
        if ($noMatch) {
            return false;
        }
        list($class, $parentClass) = explode('extends', current($output));
        $parentClass = trim($parentClass);
        if ('Mage_Core_Model_Mysql4_Abstract' == $parentClass) {
            return false;
        }
        if ('Mage_Eav_Model_Entity_Abstract' == $parentClass) {
            return true;
        }
        return $this->_isEavModel($parentClass);
    }
}

$parser = new DatabaseParser($branch, $databaseName);
$parser->run();
