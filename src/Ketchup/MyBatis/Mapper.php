<?php

namespace Ketchup\MyBatis;

use Ketchup\MyBatis\Util\Ognl;
use Ketchup\MyBatis\SqlSession;
use Ketchup\MyBatis\SQL\AbstractStatement;
use Ketchup\MyBatis\SQL\ChooseStatement;
use Ketchup\MyBatis\SQL\DeleteStatement;
use Ketchup\MyBatis\SQL\ForEachStatement;
use Ketchup\MyBatis\SQL\IfStatement;
use Ketchup\MyBatis\SQL\IncludeStatement;
use Ketchup\MyBatis\SQL\InsertStatement;
use Ketchup\MyBatis\SQL\OtherwiseStatement;
use Ketchup\MyBatis\SQL\SelectKeyStatement;
use Ketchup\MyBatis\SQL\SelectStatement;
use Ketchup\MyBatis\SQL\SetStatement;
use Ketchup\MyBatis\SQL\SqlStatement;
use Ketchup\MyBatis\SQL\TextStatement;
use Ketchup\MyBatis\SQL\UpdateStatement;
use Ketchup\MyBatis\SQL\WhenStatement;
use Ketchup\MyBatis\SQL\WhereStatement;

/**
 * php MyBatis implementation
 */
class Mapper {

    use \Psr\Log\LoggerAwareTrait;

    /** @var Mapper $instance : singleton instance */
    private static $instance;

    /** @var array $partialSql : cache of partial sql */
    private $partialSql;
    /** @var SqlSession $dbSession */
    private $dbSession;
    /** @var array $dbConfig */
    private $dbConfig;
    
    /** @var array $statements : cache of select, insert, update, delete functions */
    public $statements;

    /**
     * constructor
     */
    public function __construct() {
        $this->logger = new \Psr\Log\NullLogger();
    }

    /**
     * get singleton instance
     * @return Mapper
     */
    public static function instance() {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * get database access object
     * @return SqlSession
     */
    public function getSession() {
        if (!isset($this->dbSession)) {
            $this->logger->debug('SqlSession CREATE');
            $this->dbSession = new SqlSession($this->dbConfig);
        }
        return $this->dbSession;
    }

    /**
     * destroy database access object
     * @return void
     */
    public function destroySession() {
        if (isset($this->dbSession)) {
            $this->dbSession->destroy();
        }
    }

    /**
     * xml to statement
     *
     * @param string $namespace
     * @param \DOMElement $elem
     * @param AbstractStatement $parent
     * @return AbstractStatement
     */
    function parseXml($namespace = '', $elem, $parent = NULL) {
        $current = NULL;
        switch ($elem->nodeType) {
            case XML_TEXT_NODE:
            case XML_CDATA_SECTION_NODE:
                $text = trim($elem->textContent);
                if ($text) {
                    $current = new TextStatement($text);
                    if ($parent) {
                        $parent->append($current);
                    }
                }
                return $current;
        }
        $nodeName = $elem->nodeName;
        switch ($nodeName) {
            case 'sql':
                $current = new SqlStatement($namespace . $elem->getAttribute('id'));
                break;
            case 'selectKey':
                $current = new SelectKeyStatement($elem->getAttribute('keyProperty'), $elem->getAttribute('order'), $elem->getAttribute('resultType'));
                break;
            case 'include':
                $current = new IncludeStatement($namespace . $elem->getAttribute('refid'));
                break;
            case 'select':
                $current = new SelectStatement($namespace . $elem->getAttribute('id'), $elem->getAttribute('resultType'));
                break;
            case 'insert':
                $current = new InsertStatement($namespace . $elem->getAttribute('id'), $elem->getAttribute('useGeneratedKeys'), $elem->getAttribute('keyProperty'));
                break;
            case 'update':
                $current = new UpdateStatement($namespace . $elem->getAttribute('id'));
                break;
            case 'delete':
                $current = new DeleteStatement($namespace . $elem->getAttribute('id'));
                break;
            case 'foreach':
                $current = new ForEachStatement($elem->getAttribute('collection'), $elem->getAttribute('item'), $elem->getAttribute('index'), $elem->getAttribute('open'), $elem->getAttribute('close'), $elem->getAttribute('separator'));
                break;
            case 'choose':
                $current = new ChooseStatement();
                break;
            case 'when':
                $current = new WhenStatement($elem->getAttribute('test'));
                break;
            case 'otherwise':
                $current = new OtherwiseStatement();
                break;
            case 'if':
                $current = new IfStatement($elem->getAttribute('test'));
                break;
            case 'set':
                $current = new SetStatement();
                break;
            case 'where':
                $current = new WhereStatement();
                break;
        }
        if ($current) {
            if ($parent) {
                $parent->append($current);
            }
            if ($elem->hasChildNodes()) {
                foreach ($elem->childNodes as $child) {
                    $this->parseXml($namespace, $child, $current);
                }
            }
        }
        return $current;
    }

    /**
     * initialize mapper xml dom
     *
     * @param DOMDocument $simple_xml_dom
     * @return void
     */
    public function initXml($simple_xml_dom) {
        $root = dom_import_simplexml($simple_xml_dom);
        $namespace = trim($root->getAttribute('namespace'));
        if ($namespace) {
            $namespace .= '.';
        }
        foreach ($root->getElementsByTagName('sql') as $node) {
            $statement = $this->parseXml($namespace, $node);
            $statement->setMapper($this);
            $this->statements['sql'][$statement->getId()] = $statement;
        }
        foreach ($root->getElementsByTagName('select') as $node) {
            $statement = $this->parseXml($namespace, $node);
            $statement->setMapper($this);
            $this->statements['select'][$statement->getId()] = $statement;
        }
        foreach ($root->getElementsByTagName('insert') as $node) {
            $statement = $this->parseXml($namespace, $node);
            $statement->setMapper($this);
            $this->statements['insert'][$statement->getId()] = $statement;
        }
        foreach ($root->getElementsByTagName('update') as $node) {
            $statement = $this->parseXml($namespace, $node);
            $statement->setMapper($this);
            $this->statements['update'][$statement->getId()] = $statement;
        }
        foreach ($root->getElementsByTagName('delete') as $node) {
            $statement = $this->parseXml($namespace, $node);
            $statement->setMapper($this);
            $this->statements['delete'][$statement->getId()] = $statement;
        }
    }

    /**
     * set DB Connection config
     * array('url' => '', 'username' => '', 'password' => '')
     * 
     * @see SqlSession::__construct
     * @param array $dbConfig : DB Connection config
     * @return Mapper
     */
    public function setConnection($dbConfig) {
        $this->statements = [
            'sql' => [],
            'select' => [],
            'insert' => [],
            'update' => [],
            'delete' => []
        ];
        $this->dbConfig = $dbConfig;
        $this->destroySession();
        return $this;
    }

    /**
     * initialize "mapper" from mapper xml file
     * @param string $mapper_xml_path : full path of mapper xml file
     * @param string $cache_dir : directory full path of php code cache
     * @return Mapper
     */
    public function initMapper($mapper_xml_path, $cache_dir = NULL) {
        $this->logger->debug('INIT MAPPER START "' . $mapper_xml_path . '"');
        $cache_path = '';
        $cache_prefix = '';
        if ($cache_dir) {
            $cache_prefix = rtrim($cache_dir, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . 'mapper-cache-' . md5(realpath($mapper_xml_path));
            $cache_path = $cache_prefix . '-' . (filemtime($mapper_xml_path) + filemtime(__FILE__)) . '.php';
        }
        //코드 캐시파일이 있는 경우, 캐시파일 실행 후 종료
        if ($cache_path && file_exists($cache_path)) {
            include($cache_path);
            $this->logger->debug('INIT MAPPER COMPLETE FROM CACHE');
            return $this;
        }
        $this->initXml(simplexml_load_file($mapper_xml_path));
        if ($cache_path) {
            foreach (glob($cache_prefix . '*.php') as $cache_file) {
                @unlink($cache_file);
            }
            $cache = fopen($cache_path, 'w');
            if ($cache) {
                fwrite($cache, '<' . '?php' . PHP_EOL);
                foreach ($this->statements as $k => &$statements) {
                    foreach ($statements as &$statement) {
                        fwrite($cache, '$this->statements["' . $k . '"]["' . $statement->getId() . '"]=' . $statement->__toSource() . ';' . PHP_EOL);
                        fwrite($cache, '$this->statements["' . $k . '"]["' . $statement->getId() . '"]->setMapper($this);' . PHP_EOL);
                    }
                }
                fwrite($cache, '?' . '>');
                fclose($cache);
            } else {
                $this->logger->warning('WRITE MAPPER CACHE FAILED');
            }
        }
        $this->logger->debug('INIT MAPPER COMPLETE');
        return $this;
    }

    /**
     * initilize "configuration" from sqlmap config xml file
     * @param string $config_xml_path : full path of configuration xml file
     * @param string $cache_dir : directory full path of php code cache
     * @return Mapper
     */
    public function init($config_xml_path, $cache_dir = NULL) {
        $this->statements = [
            'sql' => [],
            'select' => [],
            'insert' => [],
            'update' => [],
            'delete' => []
        ];
                
        $sqlMapDir = dirname(realpath($config_xml_path));
        $sqlMapConfig = simplexml_load_file($config_xml_path);

        $environments = $sqlMapConfig->xpath('/configuration/environments[1]');
        if ($environments) {
            $defaultAttr = $environments[0]->xpath('@default');
            if ($defaultAttr) {
                $environment = $environments[0]->xpath('environment[@id="' . $defaultAttr[0] . '"]');
            } else {
                $environment = $environments[0]->xpath('environment[1]');
            }
            $properties = $environment[0]->xpath('dataSource/property');
            if ($properties) {
                $dataSourceConfig = [];
                foreach ($properties as $property) {
                    $attr = $property->attributes();
                    $dataSourceConfig[strval($attr['name'])] = strval($attr['value']);
                }
                $this->setConnection($dataSourceConfig);
            }
        }
        
        $mappers = $sqlMapConfig->xpath('/configuration/mappers/mapper');
        if ($mappers) {
            foreach ($mappers as $mapper) {
                $attr = $mapper->attributes();
                if ($attr && isset($attr['resource'])) {
                    $this->initMapper($sqlMapDir . \DIRECTORY_SEPARATOR . $attr['resource'], $cache_dir);
                }
            }
        }

        return $this;
    }

    /**
     * get statement object
     *
     * @param string $category
     * @param string $id
     * @return AbstractStatement
     */
    public function getStatement($category, $id) {
        if (!isset($this->statements[$category][$id])) {
            throw new \Exception('Undefined index in mapper::statements[' . $category . '][' . $id . ']');
        }
        return $this->statements[$category][$id];
    }

    /**
     * convert value to given type
     * @param mixed $value
     * @param string $type
     * @return void
     */
    public function _converTo($value, $type) {
        switch (strtolower($type)) {
            case 'bool':
            case 'boolean':
                return (bool)$value;
            case 'int':
            case 'integer':
                return (int)$value;
            case 'double':
            case 'float':
                return (float)$value;
            case 'string':
                return (string)$value;
            default:
                return $value;
        }
    }

    /**
     * get result of "select"
     * @param string $id : id of "select"
     * @param array|object $param : input parameter
     * @return array
     */
    public function select($id, $param = NULL) {
        $this->logger->debug('SELECT STATEMENT [' . $id  . '] START', [$param]);
        $statement = $this->getStatement('select', $id);
        $sqlParam = [];
        $sqlText = $statement->parse($param, $sqlParam);
        $resultType = $statement->getAttribute('resultType');
        $result = NULL;
        switch (strtolower($resultType)) {
            case 'bool':
            case 'boolean':
            case 'int':
            case 'integer':
            case 'double':
            case 'float':
            case 'string':
                $result = array_map(function (&$arr) use (&$mapper, $resultType) { /** @var mapper $mapper */
                    return $mapper->_convertTo(array_values($arr)[0], $resultType);
                }, $this->getSession()->query($sqlText, $sqlParam));
                break;
            case '':
            case 'array':
                $result = $this->getSession()->query($sqlText, $sqlParam);
                break;
            default:
                $result = $this->getSession()->queryAs($resultType, $sqlText, $sqlParam);
                break;
        }
        $this->logger->debug('SELECT STATEMENT [' . $id  . '] COMPLETE', [$sqlText, $sqlParam]);
        return $result;
    }

    /**
     * get single result of "select"
     * @param string $id : id of "select"
     * @param array|object $param : input parameter
     * @return mixed|object|NULL
     */
    public function selectOne($id, $param = NULL) {
        $this->logger->debug('SELECT STATEMENT [' . $id . '] START', [$param]);
        $statement = $this->getStatement('select', $id);
        $sqlParam = [];
        $sqlText = $statement->parse($param, $sqlParam);
        $resultType = $statement->getAttribute('resultType');
        $result = NULL;
        switch (strtolower($resultType)) {
            case 'bool':
            case 'boolean':
            case 'int':
            case 'integer':
            case 'double':
            case 'float':
            case 'string':
                $result = $this->_converTo($this->getSession()->queryValue($sqlText, $sqlParam), $resultType);
                break;
            case '':
            case 'array':
                $result = $this->getSession()->querySingle($sqlText, $sqlParam);
                break;
            default:
                $result = $this->getSession()->querySingleAs($resultType, $sqlText, $sqlParam);
                break;
        }
        $this->logger->debug('SELECT STATEMENT [' . $id  . '] COMPLETE', [$sqlText, $sqlParam]);
        return $result;
    }

    /**
     * execute "insert"
     * @param string $id : id of "insert"
     * @param array|object $param : input parameter
     * @return boolean
     */
    public function insert($id, $param = NULL) {
        $this->logger->debug('INSERT STATEMENT [' . $id . '] START', [$param]);
        $statement = $this->getStatement('insert', $id);
        /** @var SelectKeyStatement $selectKey */
        $selectKey = $statement->getSelectKey();
        if ($selectKey) {
            if ($selectKey->getAttribute('order') == 'before') {
                $sqlParam = [];
                $sqlText = $selectKey->parse($param, $sqlParam);
                $lastInsertId = $this->getSession()->queryValue($sqlText, $sqlParam);
                $keyProperty = $selectKey->getAttribute('keyProperty');
                $resultType = $selectKey->getAttribute('resultType');
                if (is_array($param)) {
                    $param[$keyProperty] = $this->_convertTo($lastInsertId, $resultType);
                } else if (is_object($param)) {
                    $param->{$keyProperty} = $this->_converTo($lastInsertId, $resultType);
                }
                $sqlParam = [];
                $sqlText = $statement->parse($param, $sqlParam);
                $result = $this->getSession()->execute($sqlText, $sqlParam);
            }
            else if ($selecKey->getAttribute('order') == 'after') {
                $sqlParam = [];
                $sqlText = $statement->parse($param, $sqlParam);
                $result = $this->getSession()->execute($sqlText, $sqlParam);
                $sqlParam = [];
                $sqlText = $selectKey->parse($param, $sqlParam);
                $lastInsertId = $this->getSession()->queryValue($sqlText, $sqlParam);
                $keyProperty = $selectKey->getAttribute('keyProperty');
                $resultType = $selectKey->getAttribute('resultType');
                if (is_array($param)) {
                    $param[$keyProperty] = $this->_convertTo($lastInsertId, $resultType);
                } else if (is_object($param)) {
                    $param->{$keyProperty} = $this->_converTo($lastInsertId, $resultType);
                }
            }
        }
        //insert 태그에 useGeneratedKeys 가 true 인 경우
        else if ($statement->getAttribute('useGeneratedKeys') === 'true') {
            $sqlParam = [];
            $sqlText = $statement->parse($param, $sqlParam);
            $result = $this->getSession()->execute($sqlText, $sqlParam);
            $lastInsertId = $this->getSession()->getLastInsertId();
            $keyProperty = $statement->getAttribute('keyProperty');
            if (is_array($param)) {
                $param[$keyProperty] = $lastInsertId;
            } else if (is_object($param)) {
                $param->{$keyProperty} = $lastInsertId;
            }
        }
        else {
            $sqlText = $statement->parse($param, $sqlParam);
            $result = $this->getSession()->execute($sqlText, $sqlParam);
        }
        $this->logger->debug('INSERT STATEMENT [' . $id  . '] COMPLETE', [$sqlText, $sqlParam]);
        return $result;
    }

    /**
     * execute "update"
     * @param string $id : id of "update"
     * @param array|object $param : input parameter
     * @return boolean
     */
    public function update($id, $param = NULL) {
        $this->logger->debug('UPDATE STATEMENT [' . $id . '] START', [$param]);
        $statement = $this->getStatement('update', $id);
        $sqlParam = [];
        $sqlText = $statement->parse($param, $sqlParam);
        $result = $this->getSession()->execute($sqlText, $sqlParam);
        $this->logger->debug('UPDATE STATEMENT [' . $id  . '] COMPLETE', [$sqlText, $sqlParam]);
        return $result;
    }

    /**
     * execute "delete"
     * @param string $id : id of "delete"
     * @param array|object $param : input parameter
     * @return boolean
     */
    public function delete($id, $param = NULL) {
        $this->logger->debug('DELETE STATEMENT [' . $id . '] START', [$param]);
        $statement = $this->getStatement('update', $id);
        $sqlParam = [];
        $sqlText = $statement->parse($param, $sqlParam);
        $result = $this->getSession()->execute($sqlText, $sqlParam);
        $this->logger->debug('DELETE STATEMENT [' . $id  . '] COMPLETE', [$sqlText, $sqlParam]);
        return $result;
    }
}