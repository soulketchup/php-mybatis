<?php

namespace Ketchup\MyBatis\SQL;

use Ketchup\MyBatis\Util\Ognl;

/**
 * where
 */
class WhereStatement extends AbstractStatement {

    /**
     * get type name of this statement
     *
     * @return string
     */
    public function getName() {
        return 'where';
    }

    /**
     * parse dynamic sql
     * (replace first "or" or "and" to "" and prepend "where" to sql query text)
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
        $query = trim($query);
        if ($query) {
            $query = ' where ' . preg_replace('/^\s*(or|and)\s+/i', ' ', $query);
        }
        return $query;
    }

    public function __toSource() {
        return 'new ' . __CLASS__ . '([' . PHP_EOL . implode(PHP_EOL . ',', array_map(function (&$child) {
            return $child->__toSource();
        }, $this->children)) . '])';
    }
}