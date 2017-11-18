# php-mybatis
php mybatis implementation

# Description
simple mybatis implementation.  
tested php version - 5.6.31-x86  
tested database vendor - mysql 5.x

# Requirements
\Psr\Log, \PDO

# Available XML Features
- configuration
  - environments[@default]
    - environment[@id]
      - dataSource
        - property[@name][@value]
  - mappers
    - mapper[@resource]
- mapper[@namespace]
  - sql[@id]
  - select[@id][@resultType]
  - insert[@id][@useGeneratedKeys][@keyProperty]
    - selectKey[@keyProperty][@resultType][@order]
  - update[@id]
  - delete[@id]
  - include[@refid]
  - if[@test]
  - choose
    - when[@test]
    - otherwise
  - where
  - set
  - foreach[@item][@index][@collection][@open][@separator][@close]

# Available OGNL Expressions
| input parameter | expression | equivalent | remark |
|---|---|---|---|
| $o = ['value1'=>'test','value2'=>10]; | "value1 != null and value2 gt 10" | $o['value1']!==NULL && $o['value2'] > 10 | |
|  | "value1.value2" | $o['value1']['value2'] | returns NULL |
|  | "value1 . value2" | $o['value1'] . $o['value2'] | insert space between dot for string concat |
| $o = new class {<br>public $arr = [1, 2, 3];<br>public $id = 1;<br>public $name = "test";<br>public function hello(){ return 'Hello ' . $this->name; }<br>}; | "arr[0] * arr[1]" | $o->arr[0] * $o->arr[1] | |
|  | "(count(arr) gt 0) and (arr[id] eq 1)" | (count($o->arr) > 0) && ($o->arr[$o->id] == 1) | operator words<br>"and, or, gt, gte, lt, lte, eq" |
