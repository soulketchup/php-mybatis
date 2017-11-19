<?php

namespace Ketchup\MyBatis\SQL;

/**
 * choose
 */
class ChooseStatement extends AbstractStatement {

    /** @var WhenStatement[] $when */
    protected $when = [];
    /** @var OtherwiseStatement[] $otherwise */
    protected $otherwise = [];

    /**
     * get type name of this statement
     *
     * @return string
     */
    public function getName() {
        return 'choose';
    }

    /**
     * append child
     *
     * @param AbstraceStatement $child
     * @return void
     */
    public function append($child) {
        $childName = $child->getName();
        if ($childName == 'when') {
            $this->when[] = $child;
        } else if ($childName == 'otherwise') {
            $this->otherwise[] = $child;
        }
        parent::append($child);
    }

    /**
     * parse dynamic sql
     * (iterate WhenStatement and OtherwiseStatement)
     *
     * @param mixed $context : input parameter
     * @param array $param : generated sql parameters
     * @param array $bindings : temp array for variables scope
     * @return string : generated sql query text
     */
    public function parse(&$context, &$param = [], &$bindings = []) {
        $this->context = &$context;
        $query = '';
        $testPassed = FALSE;
        foreach ($this->when as $child) {
            if ($this->parseExpression($child->getAttribute('test'), $context, $bindings)) {
                $query .= $child->parse($context, $param, $bindings);
                $testPassed = TRUE;
                break;
            }
        }
        if (!$testPassed) {
            foreach ($this->otherwise as $child) {
                $query .= $child->parse($context, $param, $bindings);
                break;
            }
        }
        return $query;
    }

    public function __toSource() {
        return 'new ' . __CLASS__ . '([' . PHP_EOL . implode(PHP_EOL . ',', array_map(function (&$child) {
            return $child->__toSource();
        }, $this->children)) . '])';
    }
}