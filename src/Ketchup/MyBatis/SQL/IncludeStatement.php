<?php

namespace Ketchup\MyBatis\SQL;

/**
 * include
 */
class IncludeStatement extends AbstractStatement {

    /** @var string $refid : "refid" attribute */
    protected $refid = "";

    /**
     * constructor
     *
     * @param string $refid : "refid" attribute;
     */
    public function __construct($refid) {
        $this->refid = $refid;
        $this->attributes['refid'] = $this->refid;
    }

    /**
     * get type name of this statement
     *
     * @return string
     */
    public function getName() {
        return 'include';
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
        if ($this->mapper) {
            return $this->mapper->getStatement('sql', $this->refid)->parse($context, $param , $bindings);
        }
        return ' ';
    }

    public function __toSource() {
        return 'new ' . __CLASS__ . '(\'' . $this->quote($this->refid) . '\')';
    }
}