<?php

namespace Ketchup\MyBatis\SQL;

/**
 * insert
 */
class InsertStatement extends AbstractStatement {
    
    /** @var string $useGeneratedKeys : "useGeneratedKeys" attribute */
    protected $useGeneratedKeys;
    /** @var string $keyProperty : "keyProperty" attribute */
    protected $keyProperty;
    /** @var SelectKeyStatement $selectKey : "selectKey" element */
    protected $selectKey;

    /**
     * constructor
     *
     * @param string $id : "namespace"."id"
     * @param string $useGeneratedKeys : "useGeneratedKeys" attribute
     * @param string $keyProperty : "keyProperty" attribute
     * @param AbstractStatement[] $children : child statements
     */
    public function __construct($id, $useGeneratedKeys = 'false', $keyProperty = '', $children = NULL) {
        $this->id = $id;
        $this->useGeneratedKeys = strtolower($useGeneratedKeys);
        $this->keyProperty = $keyProperty;
        $this->attributes['useGeneratedKeys'] = $this->useGeneratedKeys;
        $this->attributes['keyProperty'] = $this->keyProperty;
        parent::__construct($children);
    }

    /**
     * append child
     * (append child to this child statements except SelectKeyStatement)
     *
     * @param AbstractStatement $child
     * @return void
     */
    public function append($child) {
        if ($child->getName() == 'selectKey') {
            $this->selectKey = $child;
        }
        parent::append($child);
    }

    /**
     * get type name of this statement
     *
     * @return string
     */
    public function getName() {
        return 'insert';
    }

    /**
     * return selectKey of this insert statement
     *
     * @return SelectKeyStatement
     */
    public function getSelectKey() {
        return $this->selectKey;
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
            if ($child->getName() != 'selectKey') {
                $query .= $child->parse($context, $param, $bindings);
            }
        }
        return $query;
    }

    public function __toSource() {
        return 'new ' . __CLASS__ . '(\'' . $this->quote($this->id) . '\', \'' . $this->quote($this->useGeneratedKeys) . '\', \'' . $this->quote($this->keyProperty) . '\', [' . PHP_EOL . implode(PHP_EOL . ',', array_map(function (&$child) {
            return $child->__toSource();
        }, $this->children)) . '])';
    }
}