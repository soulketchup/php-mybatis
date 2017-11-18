<?php

namespace Ketchup\MyBatis\SQL;

/**
 * delete
 */
class DeleteStatement extends AbstractStatement {
    
    /**
     * constructor
     *
     * @param string $id : "namespace"."id"
     * @param AbstractStatement[] $children : child statements
     */
    public function __construct($id, $children = NULL) {
        $this->id = $id;
        parent::__construct($children);
    }

    /**
     * get type name of this statement
     *
     * @return string
     */
    public function getName() {
        return 'delete';
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
        return 'new ' . __CLASS__ . '([' . PHP_EOL . implode(PHP_EOL . ',', array_map(function (&$child) {
            return $child->__toSource();
        }, $this->children)) . '])';
    }
}