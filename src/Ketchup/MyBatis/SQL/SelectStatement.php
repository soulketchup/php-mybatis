<?php

namespace Ketchup\MyBatis\SQL;

/**
 * select
 */
class SelectStatement extends AbstractStatement {
    
    /** @var string $resultType : "resultType" attribute */
    protected $resultType;

    /**
     * constructor
     *
     * @param string $id : "namespace"."id"
     * @param string $resultType : "resultType" attribute
     * @param AbstractStatement[] $children : child statements
     */
    public function __construct($id, $resultType = '', $children = NULL) {
        $this->id = $id;
        $this->resultType = $resultType;
        $this->attributes['resultType'] = $this->resultType;
        parent::__construct($children);
    }

    /**
     * get type name of this statement
     *
     * @return string
     */
    public function getName() {
        return 'select';
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
        return 'new ' . __CLASS__ . '(\'' . $this->quote($this->id) . '\', \'' . $this->quote($this->resultType) . '\', [' . PHP_EOL . implode(PHP_EOL . ',', array_map(function (&$child) {
            return $child->__toSource();
        }, $this->children)) . '])';
    }
}