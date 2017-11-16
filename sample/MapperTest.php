<?php
require_once 'bootstrap.php';

use Ketchup\MyBatis\Mapper;
use Ketchup\Web\Util\BasicLogger;

class TestModel {
    public $table = 'testbed';
	public $id;
	public $subject;
	public $contents;
}

/** @var Ketchup\Web\Util\BasicLogger $logger */
$logger = new BasicLogger();
/** @var Ketchup\MyBatis\Mapper $mapper */
$mapper = Mapper::instance();
$mapper->setLogger($logger);
$mapper->init('mybatis/mybatis-config.xml');
$mapper->setConnection([ 'url' => 'mysql:dbname=test;host=127.0.0.1;charset=UTF8;port=3306;', 'username' => 'test', 'password' => 'test' ]);

$mapper->getSession()->execute('create table if not exists testbed(id int not null auto_increment, subject varchar(255), contents text, primary key(id));');

for ($i = 0; $i < 3; $i++) {
    $model = new TestModel();
    $model->subject = 'subject ' . $i;
    $model->contents = 'contents ' . $i;
    $mapper->insert('test.Create', $model);
    var_dump($model);
}

var_dump([
    'Count' => $mapper->selectOne('test.Count', ['table' => 'testbed']),
    'List' => $mapper->select('test.List', ['table' => 'testbed', 'limit' => 2, 'search' => '%1'])
]);

echo '<hr>LOG' . PHP_EOL;

echo $logger;