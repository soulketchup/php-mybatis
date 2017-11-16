<?php

namespace Ketchup\Web\Util;

class BasicLogger extends \Psr\Log\AbstractLogger {
	/** @var array $arr */
	protected $arr;

	public function __construct() {
		$this->arr = array();
	}

	public function log($level, $message, array $context = array()) {
		list($sec, $usec)  = explode('.', microtime(TRUE));
		$this->arr[] = date('Y-m-d H:i:s', $sec) . '.' . str_pad($usec, 4, '0') . ' [' . $level . '] ' . $message . (count($context) ? ' - ' . print_r($context, TRUE) : '');
	}

	public function __toString() {
		return implode(\PHP_EOL, $this->arr);
	}
}
?>