<?php

namespace Ketchup\MyBatis\SQL;

use Ketchup\MyBatis\Util\Ognl;

/**
 * if
 */
class IfStatement extends AbstractStatement {

    /** @var string $test : "test" attribute */
    protected $test = '';

    /**
     * constructor
     *
     * @param string $test : "test" attribute
     * @param AbstractStatement $children : child statements
     */
    public function __construct($test = '', $children = NULL) {
        $this->test = $test;
        $this->attributes['test'] = $test;
        parent::__construct($children);
    }

    /**
     * get type name of this statement
     *
     * @return string
     */
    public function getName() {
        return 'if';
    }

    /**
     * parse dynamic sql
     * (evaluate "test" attribute)
     *
     * @param mixed $context : input parameter
     * @param array $param : generated sql parameters
     * @param array $bindings : temp array for variables scope
     * @return string : generated sql query text
     */
    public function parse(&$context, &$param = [], &$bindings = []) {
        $this->context = &$context;
        $query = '';
        if ($this->parseExpression($this->test, $context, $bindings)) {
            foreach ($this->children as $child) {
                $query .= $child->parse($context, $param, $bindings);
            }
        }
        return $query;
    }

    public function __toSource() {
        return 'new ' . __CLASS__ . '(\'' . $this->quote($this->test) . '\', [' . PHP_EOL . implode(PHP_EOL . ',', array_map(function (&$child) {
            return $child->__toSource();
        }, $this->children)) . '])';
    }
}