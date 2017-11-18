<?php

namespace Ketchup\MyBatis\SQL;

use Ketchup\MyBatis\SQL\SelectStatement;

/**
 * selectKey
 */
class SelectKeyStatement extends SelectStatement {
    
    /** @var string $order : "order" attribute */
    protected $order = '';
    /** @var string $keyProperty : "keyProperty" attribute */
    protected $keyProperty = '';

    /**
     * constructor
     *
     * @param string $keyProperty : "keyProperty" attribute
     * @param string $order : "order" attribute
     * @param string $resultType : "resultType" attribute
     * @param AbstractStatement[] $children : child statements
     */
    public function __construct($keyProperty = '', $order = '', $resultType = '', $children = NULL) {
        $this->keyProperty = $keyProperty;
        $this->order = $order;
        $this->attributes['keyProperty'] = $this->keyProperty;
        $this->attributes['order'] = strtolower($this->order);
        parent::__construct($children, $resultType);
    }

    /**
     * get type name of this statement
     *
     * @return string
     */
    public function getName() {
        return 'selectKey';
    }

    /**
     * parse dynamic sql
     *
     * @param mixed $context : input parameter
     * @param array $param : generated sql parameters
     * @param array $bindings : temp array for variables scope
     * @return string : generated sql query text
     */
    public function parse(&$context, &$param = [], &$bindings = []) {
        $this->context = &$context;
        $query = '';
        foreach ($this->children as $child) {
            $query .= $child->parse($context, $param, $bindings);
        }
        return $query;
    }

    public function __toSource() {
        return 'new ' . __CLASS__ . '(\'' . $this->quote($this->keyProperty) . '\', \'' . $this->quote($this->order) . '\', \'' . $this->quote($this->resultType) . '\', [' . PHP_EOL . implode(PHP_EOL . ',', array_map(function (&$child) {
            return $child->__toSource();
        }, $this->children)) . '])';
    }
}