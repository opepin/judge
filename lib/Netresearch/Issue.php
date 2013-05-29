<?php
namespace Netresearch;

use \Zend_Exception;

class Issue
{
    protected $_data = array(
        'extension'   => null,
        'checkname'   => null,
        'type'        => null,
        'failed'      => null,
        'comment'     => null,
        'linenumber'  => null,
        'files'       => array(),
        'occurrences' => 1,
    );

    /**
     * Issue constructor
     * @param array $data
     */
    public function __construct(array $data = null)
    {
        if ($data) {
            $this->setData($data);
        }
    }

    /**
     * Mass data update
     *
     * @param array $data
     * @return $this
     */
    public function setData(array $data)
    {
        $this->_data = array_merge($this->_data, array_intersect_key($data, $this->_data));
        return $this;
    }

    /**
     * Magic method for set{Field} and get{Field}
     *
     * @param string $name
     * @param array $args
     * @return $this|mixed
     * @throws \Zend_Exception
     */
    public function __call($name, $args)
    {
        $action = substr($name, 0, 3);
        $field = strtolower(substr($name, 3));

        if (!in_array($action, array('set', 'get'))
            || !array_key_exists($field, $this->_data)
        ) {
            throw new \Zend_Exception(sprintf('Invalid method %s::%s', get_class($this), $name));
        }

        switch (substr($name, 0, 3)) {
            case 'set':
                if (!array_key_exists(0, $args)) {
                    throw new \Zend_Exception(sprintf('Method %s::%s requires 1 argument', get_class($this), $name));
                }
                $this->_data[$field] = $args[0];
                break;

            case 'get':
                return $this->_data[$field];
                break;
        }

        return $this;
    }

    /**
     * Compact issue data into JSON
     *
     * @return string
     */
    public function getJsonData()
    {
        $data = $this->_data;
        // Make relative path to file
        $trim = rtrim($data['extension'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($data['files']) {
            foreach ($data['files'] as $i => $file) {
                $data['files'][$i] = str_replace($trim, '', $file);
            }
        }
        $data['comment'] = str_replace($trim, '', $data['comment']);

        return json_encode($data);
    }
}