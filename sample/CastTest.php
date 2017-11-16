<?php
require_once 'bootstrap.php';

use Ketchup\Web\Util\Cast;

class FormModel {
	public $id = 0;
	public $name = '';
	private $age = 0;
	public function setAge($i) {
		$this->age = intval($i);
	}
}

$req = [
	'id' => ['10,000', '-10,000'],
	'name' => ['first', 'second', 'third'],
	'Age' => ['1']
];

echo '<pre>';

$list = Cast::toList('FormModel', $req);

var_dump($list);

$req = [
	'id' => '10',
	'name' => 'fourth',
	'Age' => '15'
];

$item = Cast::toSingle('FormModel', $req);

var_dump($item);
