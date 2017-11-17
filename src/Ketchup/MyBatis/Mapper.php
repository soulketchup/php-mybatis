<?php

namespace Ketchup\MyBatis;

use Ketchup\MyBatis\Util\Ognl;
use Ketchup\MyBatis\SqlSession;

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
    /** @var array $resultTypes : cache of resultType */
    public $resultTypes;
    /** @var array $keyProperties : cache of keyProperty */
    public $keyProperties;

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
     * parse "foreach" tag and set SQL parameter
     * @param int $i : current loop index
     * @param string $sql : translated SQL of "foreach" tag
     * @param string $collectionName : "collection" attribute value of "foreach" tag
     * @param string $indexName : "index" attribute value of "foreach" tag
     * @param mixed $index : current loop index or key
     * @param string $itemName : "item" attribute value of "foreach" tag
     * @param mixed $item : current loop item
     * @param mixed $param : input parameter object
     * @param array $sqlParam : SQL parameter
     * @return string
     */
    public function parseForEachSql($i, $sql, $collectionName, $indexName, $index, $itemName, $item, &$param, &$sqlParam) {
        $sql = preg_replace_callback('/(\$|#)\{([\s\S]+?)\}/', function ($matches) use (&$i, &$collectionName, &$indexName, &$index, &$itemName, &$item, &$param, &$sqlParam) {
            $expr = $matches[2];
            $varPrefix = strtok(trim($expr), '.');
            $isItemScope = (!empty($itemName) && $varPrefix == $itemName) || (!empty($indexName) && $varPrefix == $indexName);
            if ($isItemScope) {
                $arr = [];
                if (!empty($itemName)) {
                    $arr[$itemName] = $item;
                }
                if (!empty($indexName)) {
                    $arr[$indexName] = $index;
                }
                $v = Ognl::evaluate($arr, $expr);
            } else {
                $v = Ognl::evaluate($param, $expr);
            }
            if ($matches[1] == '$') {
                if (is_null($v)) {
                    return 'NULL';
                } else {
                    return $v;
                }
            } else {
                $sqlParamName = ':' . preg_replace('/[^\w_]+/', '_', $collectionName . '_' . $expr) . '_' . $i;
                $sqlParam[$sqlParamName] = $v;
                return $sqlParamName;
            }
        }, $sql);
        return $sql;
    }

    /**
     * parse "choose" tag to php code
     * @param string $body : string of php function body
     * @param DOMElement $node : "choose" node
     * @param string $var : name of php variable
     * @return void
     */
    private function parseChooseStatement($namespace, &$body, &$node, $var = 'sql') {
        foreach ($node->getElementsByTagName('when') as $i => $child) {
            if ($i == 0) {
                $body .= 'if (' . Ognl::parse($child->getAttribute('test')) . ') {' . PHP_EOL;
            } else {
                $body .= 'else if (' . Ognl::parse($child->getAttribute('test')) . ') {' . PHP_EOL;
            }
            $this->parseStatement($namespace, $body, $child, $var);
            $body .= '}' . PHP_EOL;
        }
        foreach ($node->getElementsByTagName('otherwise') as $i => $child) {
            $body .= 'else {' . PHP_EOL;
            $this->parseStatement($namespace, $body, $child, $var);
            $body .= '}' . PHP_EOL;
        }
    }

    /**
     * parse "where" tag to php code
     * @param string $body : string of php function body
     * @param DOMElement $node : "where" node
     * @param string $var : name of php variable
     * @return void
     */
    private function parseWhereStatement($namespace, &$body, &$node, $var = 'sql') {
        $varWhere = $var . '_where';
        $body .= '$' . $varWhere . '=\'\';' . PHP_EOL;
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->parseStatement($namespace, $body, $child, $varWhere);
            }
            $body .= 'if (!empty($' . $varWhere . ')) {' . PHP_EOL;
            $body .= '$' . $var . '.=\' where \' . preg_replace(\'/^\s?(or|and)\s+/i\', \'\', $' . $varWhere . ');' . PHP_EOL;
            $body .= '}' . PHP_EOL;
        }
    }

    /**
     * parse "set" tag to php code
     * @param string $body : string of php function body
     * @param DOMElement $node : "set" node
     * @param string $var : name of php variable
     * @return void
     */
    private function parseSetStatement($namespace, &$body, &$node, $var = 'sql') {
        $varSet = $var . '_set';
        $body .= '$' . $varSet . '=\'\';' . PHP_EOL;
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->parseStatement($namespace, $body, $child, $varSet);
            }
            $body .= 'if (!empty($' . $varSet . ')) {' . PHP_EOL;
            $body .= '$' . $var . '.=\' set \' . preg_replace(\'/^\s?,\s?|\s?,\s?$/\', \'\', $' . $varSet . ');' . PHP_EOL;
            $body .= '}' . PHP_EOL;
        }
    }

    /**
     * parse "foreach" tag to php code
     * @param string $body : string of php function body
     * @param DOMElement $node : "foreach" node
     * @param string $var : name of php variable
     * @return void
     */
    private function parseForEachStatement($namespace, &$body, &$node, $var = 'sql') {
        $varLoop = $var . '_loop';
        $collection = $node->getAttribute('collection');
        $item = $node->getAttribute('item');
        $index = $node->getAttribute('index');
        $open = $node->getAttribute('open');
        $close = $node->getAttribute('close');
        $separator = $node->getAttribute('separator');
        $body .= '$' . $varLoop . '=\'\';' . PHP_EOL;
        if ($node->hasChildNodes()) {
            $body .= '$i=0;' . PHP_EOL;
            $body .= 'foreach (' . Ognl::getClassName() . '::evaluate($param, \'' . $collection . '\') as $forEachIndex => $forEachItem) {' . PHP_EOL;
            $body .= '$temp=\'\';' . PHP_EOL;
            if (!empty($separator)) {
                $body .= '$temp.=($i > 0 ? \'' . $this->quote($separator) . '\' : \'\');' . PHP_EOL;
            }
            foreach ($node->childNodes as $child) {
                $this->parseStatement($namespace, $body, $child, 'temp');
            }
            $body .= '$' . $varLoop . '.=$this->parseForEachSql($i, $temp, \'' . $this->quote($collection) . '\', \'' . $this->quote($index) . '\', $forEachIndex, \'' . $this->quote($item) . '\', $forEachItem, $param, $sqlParam);' . PHP_EOL;
            $body .= '$i++;' . PHP_EOL;
            $body .= '}' . PHP_EOL;
        }
        $body .= 'if (!empty($' . $varLoop . ')) {' . PHP_EOL;
        $body .= '$' . $var . '.=\'' . $this->quote($open) . '\';' . PHP_EOL;
        $body .= '$' . $var . '.=$' . $varLoop . ';' . PHP_EOL;
        $body .= '$' . $var . '.=\'' . $this->quote($close) . '\';' . PHP_EOL;
        $body .= '}' . PHP_EOL;
    }

    /**
     * escape single quote
     * @param string $text : php code
     * @return string
     */
    private function quote($text) {
        return preg_replace('/\'/', '\\\'', $text);
    }

    /**
     * parse node to php
     * @param string $body : string of php function body
     * @param DOMElement $node : node
     * @param string $var : name of php variable
     * @return void
     */
    private function parseStatement($namespace, &$body, &$node, $var = 'sql') {
        switch ($node->nodeType) {
            case XML_TEXT_NODE:
            case XML_CDATA_SECTION_NODE:
                $sql = trim($node->textContent);
                if ($sql) {
                    $body .= '$' . $var . '.=\' ' . $this->quote($sql) . ' \';' . PHP_EOL;
                }
                return;
        }

        $nodeName = $node->nodeName;
        switch ($nodeName) {
            case 'include':
                $this->parseStatement($namespace, $body, $this->partialSql[$namespace . $node->getAttribute('refid')], $var);
                return;
            case 'choose':
                $this->parseChooseStatement($namespace, $body, $node, $var);
                return;
            case 'where':
                $this->parseWhereStatement($namespace, $body, $node, $var);
                return;
            case 'set':
                $this->parseSetStatement($namespace, $body, $node, $var);
                return;
            case 'foreach':
                $this->parseForEachStatement($namespace, $body, $node, $var);
                return;
            case 'selectKey':
                return;
            case 'if':
                $body .= 'if (' . Ognl::parse($node->getAttribute('test')) . ') {' . PHP_EOL;
                break;
        }

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->parseStatement($namespace, $body, $child, $var);
            }
        }

        switch ($nodeName) {
            case 'if':
                $body .= '}' . PHP_EOL;
                break;
        }
    }

    /**
     * parse "selectKey" tag to php
     * @param DOMElement $node : "selectKey" node
     * @param string $body : string php function body
     * @param string $id : id attribute of insert node
     * @return void
     */
    private function parseSelectKeyNode(&$node, &$body, $id) {
        $keyProperty = $node->getAttribute('keyProperty');
        $resultType = $node->getAttribute('resultType');
        $order = strtolower($node->getAttribute('order'));
        $selectKeyId = $id . '-' . $order;
        $this->resultTypes[$selectKeyId] = $resultType;
        $this->keyProperties[$id] = $keyProperty;
        $body .= '$this->addStatement(\'selectKey\', \'' . $selectKeyId . '\', function (&$param, &$sqlParam) {' . PHP_EOL;
        $body .= '$sql=\'\';' . PHP_EOL;
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->parseStatement($body, $child, 'sql');
            }
        }
        $body .= 'return $sql;' . PHP_EOL;
        $body .= '});' . PHP_EOL;
    }

    /**
     * parse "select", "insert", "update", "delete" tag to php function
     * @param DOMElement $node : node
     * @param string $body : string of php function body
     * @return void
     */
    private function parseSqlNode($namespace, $node, &$body) {
        $category = $node->tagName;
        $id = $namespace . $node->getAttribute('id');
        $resultType = $node->getAttribute('resultType');
        if ($category == 'insert') {
            if ($node->getAttribute('useGeneratedKeys') === 'true') {
                $keyProperty = $node->getAttribute('keyProperty');
                if ($keyProperty) {
                    $this->keyProperties[$id] = $keyProperty;
                }
            }
            else {
                $selectKey = $node->getElementsByTagName('selectKey');
                if ($selectKey->length > 0) {
                    $selectKeyNode = $selectKey->item(0);
                    $this->parseSelectKeyNode($selectKeyNode, $body, $id);
                }
            }
        }
        $this->resultTypes[$id] = $resultType;
        $body .= '$this->addStatement(\'' . $category . '\', \'' . $id . '\', function (&$param, &$sqlParam) {' . PHP_EOL;
        $body .= '$sql=\'\';' . PHP_EOL;
        $this->parseStatement($namespace, $body, $node);
        $body .= 'return $sql;' . PHP_EOL;
        $body .= '});' . PHP_EOL;
    }

    /**
     * import "select", "insert", "update", "delete" functions
     * (calling from cached php file)
     * @internal
     * @param string $category : select, insert, update, delete
     * @param string $id : id of SQL
     * @param Closure $func : php function
     * @return void
     */
    public function addStatement($category, $id, $func) {
        $this->statements[$category][$id] = $func;
        $this->logger->debug(strtoupper($category) . ' STATEMENT [' . $id . '] ADD COMPLETE');
    }

    /**
     * initialize "mapper" from simple xml dom
     * @param string $simple_xml_dom : xml dom of "mapper"
     * @param string $translated : translated php code
     * @return void
     */
    public function initXml($simple_xml_dom, &$translated = '') {
        $this->statements = [
            'select' => [],
            'insert' => [],
            'update' => [],
            'delete' => [],
            'selectKey' => []
        ];
        $this->resultTypes = [];
        $this->partialSql = [];

        $root = dom_import_simplexml($simple_xml_dom);
        $namespace = trim($root->getAttribute('namespace'));
        if ($namespace) {
            $namespace .= '.';
        }
        foreach ($root->getElementsByTagName('sql') as $node) {
            $this->partialSql[$namespace . $node->getAttribute('id')] = $node;
        }
        $body = '';
        foreach ($root->getElementsByTagName('select') as $node) {
            $this->parseSqlNode($namespace, $node, $body);
        }
        foreach ($root->getElementsByTagName('insert') as $node) {
            $this->parseSqlNode($namespace, $node, $body);
        }
        foreach ($root->getElementsByTagName('update') as $node) {
            $this->parseSqlNode($namespace, $node, $body);
        }
        foreach ($root->getElementsByTagName('delete') as $node) {
            $this->parseSqlNode($namespace, $node, $body);
        }
        $translated = $body;
        $f = create_function('', 'return function(){' . $body . '};');
        if ($f) {
            $c = \Closure::bind($f(), $this);
            $c();
        } else {
            throw new \Exception(__CLASS__ . '::initXml() failed');
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
        $this->initXml(simplexml_load_file($mapper_xml_path), $translated);
        if ($cache_path) {
            foreach (glob($cache_prefix . '*.php') as $cache_file) {
                @unlink($cache_file);
            }
            $cache = fopen($cache_path, 'w');
            if ($cache) {
                fwrite($cache,
                    '<' . '?php' . PHP_EOL .
                    $translated . PHP_EOL .
                    '$this->resultTypes = ' . var_export($this->resultTypes, TRUE) . ';' . PHP_EOL .
                    '$this->keyProperties = ' . var_export($this->keyProperties, TRUE) . ';' . PHP_EOL .
                    '?' . '>'
                );
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
     * get plain SQL text of given id
     * @param string $category : select, insert, update, delete
     * @param string $id : id of mapper xml node
     * @param array|object $param : input parameter
     * @param array $sqlParam : SQL parameter
     * @return string
     */
    public function getSql($category, $id, $param = NULL, &$sqlParam = NULL) {
        if (!isset($this->statements[$category][$id])) {
            throw new \Exception('Undefined index in mapper::statements[' . $category . '][' . $id . ']');
        }
        $func = $this->statements[$category][$id];
        $sqlParam = [];
        $sql = trim($func($param, $sqlParam));
        return $this->getSqlText($sql, $param, $sqlParam);
    }

    /**
     * get plain SQL text from prepared SQL
     * change "#{}", "${}" to SQL parameter
     * @param string $sql : prepared SQL
     * @param array|object $param : input parameter
     * @param array $sqlParam : SQL parameter
     * @return string
     */
    private function getSqlText($sql, &$param, &$sqlParam) {
        $sqlText = preg_replace_callback('/(\$|#)\{\s*([.\s\S]+?)\s*\}/', function ($matches) use (&$param, &$sqlParam) {
            $prefix = $matches[1];
            $expr = $matches[2];
            $name = trim(preg_replace('/[^\w]/', '_', $expr), '_');
            if (is_scalar($param)) {
                $v = $param;
            } else {
                $v = Ognl::evaluate($param, $expr);
            }
            if ($prefix == '$') {
                if (is_null($v)) {
                    return 'NULL';
                } else {
                    return $v;
                }
            } else {
                $sqlParam[':' . $name] = $v;
                return ':' . $name;
            }
        }, $sql);
        return $sqlText;
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
        $sqlText = $this->getSql('select', $id, $param, $sqlParam);
        $resultType = $this->resultTypes[$id];
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
        $sqlText = $this->getSql('select', $id, $param, $sqlParam);
        $resultType = $this->resultTypes[$id];
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
     * @return void
     */
    public function insert($id, $param = NULL) {
        $this->logger->debug('INSERT STATEMENT [' . $id . '] START', [$param]);
        //insert 태그에 selectKey[order="before"] 가 있는 경우
        if (isset($this->statements['selectKey'][$id . '-before'])) {
            $selectKeyId = $id . '-before';
            $sqlSelectKeyText = $this->getSql('selectKey', $selectKeyId, $param, $sqlKeyParam);
            $lastInsertId = $this->getSession()->queryValue($sqlSelectKeyText, $sqlKeyParam);
            $keyProp = $this->keyProperties[$id];
            $resultType = $this->resultTypes[$selectKeyId];
            if (is_array($param)) {
                $param[$keyProp] = $this->_converTo($lastInsertId, $resultType);
            } else if (is_object($param)) {
                $param->{$keyProp} = $this->_converTo($lastInsertId, $resultType);
            }
            $sqlText = $this->getSql('insert', $id, $param, $sqlParam);
            $this->getSession()->execute($sqlText, $sqlParam);
        }
        //insert 태그에 selectKey[order="after"] 가 있는 경우
        else if (isset($this->statements['selectKey'][$id . '-after'])) {
            $sqlText = $this->getSql('insert', $id, $param, $sqlParam);
            $this->getSession()->execute($sqlText, $sqlParam);
            $selectKeyId = $id . '-after';
            $sqlSelectKeyText = $this->getSql('selectKey', $selectKeyId, $param, $sqlKeyParam);
            $lastInsertId = $this->getSession()->queryValue($sqlSelectKeyText, $sqlKeyParam);
            $keyProp = $this->keyProperties[$id];
            $resultType = $this->resultTypes[$selectKeyId];
            if (is_array($param)) {
                $param[$keyProp] = $this->_converTo($lastInsertId, $resultType);
            } else if (is_object($param)) {
                $param->{$keyProp} = $this->_converTo($lastInsertId, $resultType);
            }
        }
        //insert 태그에 useGeneratedKeys 가 true 인 경우
        else if (isset($this->keyProperties[$id])) {
            $sqlText = $this->getSql('insert', $id, $param, $sqlParam);
            $this->getSession()->execute($sqlText, $sqlParam);
            $lastInsertId = $this->getSession()->getLastInsertId();
            $keyProp = $this->keyProperties[$id];
            if (is_array($param)) {
                $param[$keyProp] = $lastInsertId;
            } else if (is_object($param)) {
                $param->{$keyProp} = $lastInsertId;
            }
        }
        else {
            $sqlText = $this->getSql('insert', $id, $param, $sqlParam);
            $this->getSession()->execute($sqlText, $sqlParam);
        }
        $this->logger->debug('INSERT STATEMENT [' . $id  . '] COMPLETE', [$sqlText, $sqlParam, $param]);
    }

    /**
     * execute "update"
     * @param string $id : id of "update"
     * @param array|object $param : input parameter
     * @return void
     */
    public function update($id, $param = NULL) {
        $this->logger->debug('UPDATE STATEMENT [' . $id . '] START', [$param]);
        $sqlText = $this->getSql('update', $id, $param, $sqlParam);
        $result = $this->getSession()->execute($sqlText, $sqlParam);
        $this->logger->debug('UPDATE STATEMENT [' . $id  . '] COMPLETE', [$sqlText, $sqlParam]);
        return $result;
    }

    /**
     * execute "delete"
     * @param string $id : id of "delete"
     * @param array|object $param : input parameter
     * @return void
     */
    public function delete($id, $param = NULL) {
        $this->logger->debug('DELETE STATEMENT [' . $id . '] START', [$param]);
        $sqlText = $this->getSql('delete', $id, $param, $sqlParam);
        $result = $this->getSession()->execute($sqlText, $sqlParam);
        $this->logger->debug('DELETE STATEMENT [' . $id  . '] COMPLETE', [$sqlText, $sqlParam]);
        return $result;
    }
}