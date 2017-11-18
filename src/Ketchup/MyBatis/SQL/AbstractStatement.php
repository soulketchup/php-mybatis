<?php

namespace Ketchup\MyBatis\SQL;

use Ketchup\MyBatis\Mapper;
use Ketchup\MyBatis\Util\Ognl;

/**
 * abstract class for dynamic sql
 */
abstract class AbstractStatement {

    /** @var string $id : id of this statement */
    protected $id = '';
    /** @var AbstractStatement $parent : parent statement */
    protected $parent = NULL;
    /** @var AbstractStatement[] $children : child statements */
    protected $children = [];
    /** @var mixed $context : input parameter */
    protected $context = NULL;
    /** @var int $paramIndex : auto increment number to generate sql parameter name */
    protected $paramIndex = 0;
    /** @var array $attributes : attributes */
    protected $attributes = [];
    /** @var Mapper $mapper */
    protected $mapper = NULL;

    /**
     * constructor
     *
     * @param AbstractStatement[] $children
     */
    public function __construct($children = NULL) {
        if (is_array($children)) {
            foreach ($children as $child) {
                $this->append($child);
            }
        }
    }

    /**
     * set mapper of this statement
     *
     * @param Mapper $mapper
     * @return void
     */
    public function setMapper(&$mapper) {
        $this->mapper = $mapper;
        foreach ($this->children as $child) {
            $child->setMapper($mapper);
        }
    }

    /**
     * get attribute of this statement
     *
     * @param string $name : attribute name
     * @return string
     */
    public function getAttribute($name) {
        return isset($this->attributes[$name]) ? $this->attributes[$name] : '';
    }

    /**
     * append child
     *
     * @param AbstractStatement $child
     * @return void
     */
    public function append($child) {
        $child->setParent($this);
        $this->children[] = $child;
    }

    /**
     * set $this->parent with givent AbstractStatement
     *
     * @param AbstractStatement $parent
     * @return void
     */
    public function setParent($parent) {
        $this->parent = $parent;
    }

    /**
     * get $this->parent
     *
     * @return AbstractStatement
     */
    public function getParent() {
        return $this->parent;
    }

    /**
     * get $this->context
     *
     * @return mixed
     */
    public function getContext() {
        return $this->context;
    }

    /**
     * get $this->id
     *
     * @return string
     */
    public function getId() {
        return $this->id;
    }

    /**
     * get generated sql parameter name
     *
     * @param string $expr
     * @return string
     */
    public function makeSqlParamName($expr) {
        return ':' . preg_replace('/[^\w]/', '_', $expr) . '_' . ($this->paramIndex++);
    }

    /**
     * get evaluated value with given OGNL expression
     *
     * @param string $expr
     * @param mixed $context
     * @param array $bindings
     * @return mixed
     */
    public function parseExpression(&$expr, &$context, &$bindings) {
        $token1 = strtok($expr, '.');
        if (isset($bindings[$token1])) {
            return Ognl::evaluate($bindings, $expr);
        }
        else if (is_scalar($context)) {
            return $context;
        }
        else {
            return Ognl::evaluate($context, $expr);
        }        
    }

    /**
     * get type name of this statement
     *
     * @return string
     */
    public abstract function getName();

    /**
     * parse dynamic sql
     *
     * @param mixed $context : input parameter
     * @param array $param : generated sql parameters
     * @param array $bindings : temp array for variables scope
     * @return string : generated sql query text
     */
    public abstract function parse(&$context, &$param = [], &$bindings = []);

    /**
     * escape single quote
     *
     * @return string
     */
    protected function quote($s) {
        return preg_replace('/\'/', '\'', $s);
    }

    /**
     * get php code
     *
     * @return string
     */
    public abstract function __toSource();
}
