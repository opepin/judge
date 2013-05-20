<?php
/**
 * Generate tag file for use with MageCompatibility plugin for Judge
 */

if (count($argv) !== 2) {
    die('Please submit exactly one param: The path to the Magento root directory' . PHP_EOL);
}
$magentoDir = $argv[1];
if (substr($magentoDir, -1) !== '/') {
    $magentoDir .= '/';
}
if (!file_exists($magentoDir . '/app/Mage.php')) {
    die('Are you sure, there is a Magento? Couldn\'t find Mage.php!' . PHP_EOL);
}

$tagger = new Tagger($magentoDir);
$tagger->run();

class Tagger
{
    protected $_magentoDir;
    protected $_tagFile;
    protected $_edition;
    protected $_version;

    protected $_needed = array(
        'function' => array(
            'token_node' => T_FUNCTION,
            'name_node'  => T_STRING,
            'node_type' => 'f',
            'steps_to_name' => 1,
        ),
        'class' => array(
            'token_node' => T_CLASS,
            'name_node'  => T_STRING,
            'node_type' => 'c',
            'steps_to_name' => 1,
        ),
        'define' => array(
            'token_node' => T_STRING,
            'name_node'  => T_CONSTANT_ENCAPSED_STRING,
            'node_type' => 'd',
            'steps_to_name' => 2,
        ),
        'interface' => array(
            'token_node' => T_INTERFACE,
            'name_node'  => T_STRING,
            'node_type' => 'i',
            'steps_to_name' => 1,
        ),
    );

    /**
     * @param string $magentoDir
     */
    public function __construct($magentoDir)
    {
        $this->_magentoDir = $magentoDir;
        $this->_verifyMagento();
        $this->_tagFile = realpath(dirname(__FILE__) . '/../var/tags/')
            . '/' . strtolower($this->_edition) . '-' . $this->_version . '.tags';
    }

    /**
     * Run php-parser
     */
    public function run()
    {
        echo 'Analyzing Magento ' . $this->_version . ' (' . $this->_edition . ' Edition)...' . PHP_EOL;
        $command = sprintf(
            'find %s -type f -name "*.ph*" -print 2>/dev/null',
            $this->_magentoDir
        );
        exec($command, $files);
        $tagFile = fopen($this->_tagFile, 'w');
        foreach ($files as $file) {
            $source = file_get_contents($file);
            // tokenize php-source and remove all white spaces
            $tokenized = array_values(array_filter(token_get_all($source), function($node) {
                return !is_array($node) || $node[0] !== T_WHITESPACE;
            }));
            // split source code on lines
            $source = explode("\n", $source);
            foreach ($tokenized as $i => $node) {
                // skip single character of tokens identifiers
                if (!is_array($node)) {
                    continue;
                }
                foreach ($this->_needed as $type => $item) {
                    // check if that node neede to be collected
                    if ($node[0] !== $item['token_node'] || $node[1] !== $type) {
                        continue;
                    }
                    $nameNode = $tokenized[$i + $item['steps_to_name']];
                    if ($nameNode[0] !== $item['name_node']) {
                        continue;
                    }
                    if ($node[1] === 'function') {
                        $codeLine = $this->_functionDeclarationCodeLine($i, $tokenized, $source);
                    } else {
                        $codeLine = $source[$node[2] - 1];
                    }
                    $row = implode("\t", array(
                        trim($nameNode[1], '\'"'),
                        substr($file, strlen($this->_magentoDir)),
                        '/^' . str_replace('/', '\/', trim($codeLine)) . '$/;"',
                        $item['node_type'],
                        'line:' . $node[2]
                    ));
                    fputs($tagFile, $row . "\n");
                    break;
                }
            }
        }
        fclose($tagFile);
    }

    /**
     * Define magento version and edition
     */
    protected function _verifyMagento()
    {
        include $this->_magentoDir . 'app/Mage.php';
        $this->_version = Mage::getVersion();
        if (method_exists('Mage', 'getEdition')) {
            $this->_edition = Mage::getEdition();
        } else {
            $this->_edition = file_exists($this->_magentoDir . 'app/etc/enterprise.xml') ?
                'Enterprise' : 'Community';
        }
    }

    /**
     * Combine function declaration (if it was in several lines) in single string
     * @param int $i index
     * @param array $tokenized
     * @param array $source array of code lines
     * @return string
     */
    protected function _functionDeclarationCodeLine($i, &$tokenized, &$source) {
        $j = $i + 1;
        // declaration is ended
        while (isset($tokenized[$j]) && $tokenized[$j] !== ')') {
            $j++;
        }
        $j--;
        // search for last declaration node
        while (isset($tokenized[$j]) && !is_array($tokenized[$j])) {
            $j--;
        }
        // concatenation all lines in one line
        $codeLine = implode(' ', array_slice(
            $source,
            $tokenized[$i][2] - 1,
            $tokenized[$j][2] - ($tokenized[$i][2] - 1)
        ));
        if (substr_count($codeLine, '(') > substr_count($codeLine, ')')) {
            $codeLine .= ')';
        }
        $codeLine = str_replace(array("\n", "\r", "\t"), array('', '', ''), $codeLine);
        // this cleaner came from previous parser
        $codeLine = preg_replace('/".*"/U', '""', $codeLine);
        $codeLine = preg_replace('/\'.*\'/U', '\'\'', $codeLine);
        return $codeLine;
    }
}
