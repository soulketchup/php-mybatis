<?php

namespace Ketchup\MyBatis\SQL;

use Ketchup\MyBatis\Util\Ognl;

/**
 * query text
 */
class TextStatement extends AbstractStatement {
    
    /** @var string $sqlText : prepared query text */
    private $sqlText = '';

    /**
     * get type name of this statement
     *
     * @return void
     */
    public function getName() {
        return 'text';
    }

    /**
     * constructor
     *
     * @param string $sqlText : prepared query text
     */
    public function __construct($sqlText) {
        $this->sqlText = $sqlText;
    }

    /**
     * parse dynamic sql
     * (find ${} and replace with evaluated value, find #{} and replace with sql parameter name and add evaluated value to sql parameter array)
     *
     * @param mixed $context : input parameter
     * @param array $param : generated sql parameters
     * @param array $bindings : temp array for variables scope
     * @return string : generated sql query text
     */
    public function parse(&$context, &$param = [], &$bindings = []) {
        $this->context = &$context;
        $query = preg_replace_callback('/([#$]){([\s\S]+?)}/', function ($match) use (&$context, &$param, &$bindings) {
            $expr = $match[2];
            $v = $this->parseExpression($expr, $context, $bindings);
            if ($match[1] == '$') {
                return is_null($v) ? 'NULL' : strval($v);
            } else {
                $paramName = $this->makeSqlParamName($expr);
                $param[$paramName] = $v;
                return $paramName;
            }
        }, $this->sqlText);
        return $query . ' ';
    }

    public function __toSource() {
        return 'new ' . __CLASS__ . '(\'' . $this->quote($this->sqlText) . '\')';
    }
}