<?php

namespace Ketchup\MyBatis;

use Ketchup\MyBatis\Util\Ognl;
use Ketchup\MyBatis\SqlSession;
use Ketchup\MyBatis\SqlMap;
use Ketchup\MyBatis\SQL\AbstractStatement;
use Ketchup\MyBatis\SQL\InsertStatement;
use Ketchup\MyBatis\SQL\SelectKeyStatement;
use Ketchup\MyBatis\SQL\SelectStatement;
use Ketchup\MyBatis\SQL\UpdateStatement;
use Ketchup\MyBatis\SQL\DeleteStatement;

/**
 * php MyBatis implementation
 */
class Mapper {

    use \Psr\Log\LoggerAwareTrait;

    /** @var Mapper $instance : singleton instance */
    private static $instance;

    /** @var SqlSession $dbSession */
    private $dbSession;
    /** @var array $dbConfig */
    private $dbConfig;
    /** @var SqlMap */
    private $sqlMap;
    
    /**
     * constructor
     */
    public function __construct() {
        $this->logger = new \Psr\Log\NullLogger();
        $this->sqlMap = new SqlMap();
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
     * get SqlMap instance
     * @return SqlMap
     */
    public function getMap() {
        return $this->sqlMap;
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
     * initilize "configuration" from sqlmap config xml file
     * @param string $config_xml_path : full path of configuration xml file
     * @param string $cache_dir : directory full path of php code cache
     * @return Mapper
     */
    public function init($config_xml_path, $cache_dir = NULL) {
        $this->sqlMap->resetMappers();

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
                    $this->sqlMap->loadMapper($sqlMapDir . \DIRECTORY_SEPARATOR . $attr['resource'], $cache_dir);
                }
            }
        }

        return $this;
    }

    /**
     * convert value to given type
     * @param mixed $value
     * @param string $type
     * @return void
     */
    public function _convertTo($value, $type) {
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
        $statement = $this->sqlMap->getStatement('select', $id);
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
        $statement = $this->sqlMap->getStatement('select', $id);
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
                $result = $this->_convertTo($this->getSession()->queryValue($sqlText, $sqlParam), $resultType);
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
        $statement = $this->sqlMap->getStatement('insert', $id);
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
                    $param->{$keyProperty} = $this->_convertTo($lastInsertId, $resultType);
                }
                $sqlParam = [];
                $sqlText = $statement->parse($param, $sqlParam);
                $result = $this->getSession()->execute($sqlText, $sqlParam);
            }
            else if ($selectKey->getAttribute('order') == 'after') {
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
                    $param->{$keyProperty} = $this->_convertTo($lastInsertId, $resultType);
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
        $statement = $this->sqlMap->getStatement('update', $id);
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
        $statement = $this->sqlMap->getStatement('update', $id);
        $sqlParam = [];
        $sqlText = $statement->parse($param, $sqlParam);
        $result = $this->getSession()->execute($sqlText, $sqlParam);
        $this->logger->debug('DELETE STATEMENT [' . $id  . '] COMPLETE', [$sqlText, $sqlParam]);
        return $result;
    }
}