<?php
require_once 'bootstrap.php';

$obj = [
	'test' => 1,
	'test2' => (object)[
		'name' => 'test',
		'id' => 222
	]
];

use Ketchup\MyBatis\Util\Ognl as ognl;

var_dump(ognl::evaluate($obj, 'test'));
var_dump(ognl::evaluate($obj, 'test2.name'));
var_dump(ognl::evaluate($obj, 'test2.name != "test"'));
var_dump(ognl::evaluate($obj, 'test2.id'));
var_dump(ognl::evaluate($obj, 'test2.id gt 100'));
var_dump(ognl::evaluate($obj, "test2.id . ' ' . test2.name"));

var_dump(ognl::parse('test.test().test2(test2, test3)["123"][0]'));
var_dump(ognl::parse('((test != null and test.test gt 0) or isNotEmpty(test2) and !empty(test))'));