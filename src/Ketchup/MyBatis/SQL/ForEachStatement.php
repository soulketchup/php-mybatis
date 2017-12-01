<?php

namespace Ketchup\MyBatis\SQL;

use Ketchup\MyBatis\Util\Ognl;

/**
 * foreach
 */
class ForEachStatement extends AbstractStatement {

    /** @var string $collection : "collection" attribute */
    protected $collection;
    /** @var string $item : "item" attribute */
    protected $item;
    /** @var string $index : "index" attribute */
    protected $index;
    /** @var string $open : "open" attribute */
    protected $open;
    /** @var string $close : "close" attribute */
    protected $close;
    /** @var string $separator : "separator" attribute */
    protected $separator;

    /**
     * constructor
     *
     * @param string $collection : "collection" attribute
     * @param string $item : "item" attribute
     * @param string $index : "index" attribute
     * @param string $open : "open" attribute
     * @param string $close : "close" attribute
     * @param string $separator : "separator" attribute
     * @param AbstractStatement[] $children : child statements
     */
    public function __construct($collection, $item, $index, $open, $close, $separator, $children = NULL) {
        $this->collection = $collection;
        $this->item = $item;
        $this->index = $index;
        $this->open = $open;
        $this->close = $close;
        $this->separator = $separator;
        parent::__construct($children);
    }

    /**
     * get type name of this statement
     *
     * @return string
     */
    public function getName() {
        return 'foreach';
    }

    /**
     * parse dynamic sql
     * (add some variables to $bindings for nested statement)
     *
     * @param mixed $context : input parameter
     * @param array $param : generated sql parameters
     * @param array $bindings : temp array for variables scope
     * @return string : generated sql query text
     */
    public function parse(&$context, &$param = [], &$bindings = []) {
        $this->context = &$context;
        $query = [];
        $i = 0;
        $open = $this->open;
        $close = $this->close;
        $separator = $this->separator;
        if (empty($this->collection))
        {
            $list = $context;
        }
        else
        {
            $list = $this->parseExpression($this->collection, $context, $bindings);
        }
        foreach ($list as $k => &$v) {
            $query2 = [];
            if ($this->item) {
                $bindings[$this->item] = $v;
            }
            if ($this->index) {
                $bindings[$this->index] = $k;
            }
            foreach ($this->children as $child) {
                $query2[] = $child->parse($context, $param, $bindings);
            }
            if ($this->item) {
                unset($bindings[$this->item]);
            }
            if ($this->index) {
                unset($bindings[$this->index]);
            }
            $query[] = implode('', $query2);
        }
        return $open . implode($separator, $query) . $close . ' ';
    }

    public function __toSource() {
        return 'new ' . __CLASS__ . '(\'' . $this->quote($this->collection) . '\', \'' . $this->quote($this->item) . '\', \'' . $this->quote($this->index) . '\', \'' . $this->quote($this->open) . '\', \'' . $this->quote($this->close) . '\', \'' . $this->quote($this->separator) . '\', [' . PHP_EOL . implode(PHP_EOL . ',', array_map(function (&$child) {
            return $child->__toSource();
        }, $this->children)) . '])';
    }
}