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
 * Dynamic sql
 */
class SqlMap {
    /** @var array $partialSql : cache of partial sql */
    private $partialSql;

    /** @var string $namedParameterPrefix : parameter prefix */
    private $namedParameterPrefix = ':';

    /** @var array $statements : cache of select, insert, update, delete functions */
    public $statements;

    /**
     * constructor
     */
    public function __construct() {
        $this->resetMappers();
    }

    /**
     * reset dynamic sql
     *
     * @return void
     */
    public function resetMappers() {
        $this->statements = [
            'sql' => [],
            'select' => [],
            'insert' => [],
            'update' => [],
            'delete' => []
        ];
    }

    /**
     * set named parameter prefix
     * (set empty string use "?" for parameter)
     *
     * @param string $namedParameterPrefix
     * @return void
     */
    public function setNamedParameterPrefix($namedParameterPrefix) {
        $this->namedParameterPrefix = $namedParameterPrefix;
    }

    /**
     * get named parameter prefix
     *
     * @return string
     */
    public function getNamedParameterPrefix() {
        return $this->namedParameterPrefix;
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
            $statement->setSqlMap($this);
            $this->statements['sql'][$statement->getId()] = $statement;
        }
        foreach ($root->getElementsByTagName('select') as $node) {
            $statement = $this->parseXml($namespace, $node);
            $statement->setSqlMap($this);
            $this->statements['select'][$statement->getId()] = $statement;
        }
        foreach ($root->getElementsByTagName('insert') as $node) {
            $statement = $this->parseXml($namespace, $node);
            $statement->setSqlMap($this);
            $this->statements['insert'][$statement->getId()] = $statement;
        }
        foreach ($root->getElementsByTagName('update') as $node) {
            $statement = $this->parseXml($namespace, $node);
            $statement->setSqlMap($this);
            $this->statements['update'][$statement->getId()] = $statement;
        }
        foreach ($root->getElementsByTagName('delete') as $node) {
            $statement = $this->parseXml($namespace, $node);
            $statement->setSqlMap($this);
            $this->statements['delete'][$statement->getId()] = $statement;
        }
    }

    /**
     * initialize "mapper" from mapper xml file
     * @param string $mapper_xml_path : full path of mapper xml file
     * @param string $cache_dir : directory full path of php code cache
     * @return Mapper
     */
    public function loadMapper($mapper_xml_path, $cache_dir = NULL) {
        $cache_path = '';
        $cache_prefix = '';
        if ($cache_dir) {
            $cache_prefix = rtrim($cache_dir, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . 'mapper-cache-' . md5(realpath($mapper_xml_path));
            $cache_path = $cache_prefix . '-' . (filemtime($mapper_xml_path) + filemtime(__FILE__)) . '.php';
        }
        //코드 캐시파일이 있는 경우, 캐시파일 실행 후 종료
        if ($cache_path && file_exists($cache_path)) {
            include($cache_path);
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
}