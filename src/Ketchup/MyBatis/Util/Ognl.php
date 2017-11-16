<?php

namespace Ketchup\MyBatis\Util;

/**
 * OGNL expression parser/evaluator
 */
class Ognl {

	const NONE = '';
	const START = 'start';
	const ARRAY_END = 'array end';
	const METHOD_START = 'method start';
	const METHOD_END = 'method end';
	const OPERATOR = 'operator';
	const NUM = 'number';
	const STR = 'string';
	const PROP = 'property';
	const CONSTANT = 'constant';

	/** @var string $globalFunctions : global functions */
	private static $globalFunctionPattern = '/^(empty|count|strlen|mb_strlen)$/';
	
	/** @var array $exprCache : cache of code */
	private static $exprCache;
	
	/** @var array $evalCache : cache of evaluate */
	private static $evalCache;

	/**
	 * get php code from cache of code
	 * @param string $expr : OGNL expression
	 * @return string|NULL
	 */
	private static function getExprCache($expr) {
		if (isset(self::$exprCache) && isset(self::$exprCache[$expr])) {
			return self::$exprCache[$expr];
		}
		return NULL;
	}

	/**
	 * set php code to cache of code
	 * @param string $expr : OGNL expression
	 * @param string $code : translated php code
	 * @return void
	 */
	private static function setExprCache($expr, $code) {
		if (!isset(self::$exprCache)) {
			self::$exprCache = [];
		}
		self::$exprCache[$expr] = $code;
	}

	/**
	 * get function from cache of evaluate
	 * @param string $expr : OGNL expression
	 * @return Closure|NULL
	 */
	private static function getEvalCache($expr) {
		if (isset(self::$evalCache) && isset(self::$evalCache[$expr])) {
			return self::$evalCache[$expr];
		}
		return NULL;
	}

	/**
	 * set function to cache of evaluate
	 * @param string $expr : OGNL expression
	 * @param Closure $func
	 * @return void
	 */
	private static function setEvalCache($expr, &$func) {
		if (!isset(self::$evalCache)) {
			self::$evalCache = [];
		}
		self::$evalCache[$expr] = $func;
	}

	/**
	 * get value from context
	 * @param mixed $param : context
	 * @param array $propertyChain : array of property name
	 * @return mixed
	 */
	public static function v(&$param, $propertyChain = NULL) {
		$v = $param;
		if (is_array($propertyChain)) {
			foreach ($propertyChain as $propertyName) {
				if (is_array($v) && array_key_exists($propertyName, $v)) {
					$v = $v[$propertyName];
				} else if (is_object($v) && property_exists($v, $propertyName)) {
					$v = $v->{$propertyName};
				} else {
					return NULL;
				}
			}
		}
		return $v;
	}

	/**
	 * evaluate php code from OGNL expression
	 * @param array|object $param
	 * @param string $expr
	 * @return mixed
	 */
	public static function evaluate(&$param, $expr) {
		$func = self::getEvalCache($expr);
		if (is_null($func)) {
			$code = self::parse($expr);
			$func = create_function('&$param', 'return ' . $code . ';');
			self::setEvalCache($expr, $func);
		}
		return $func($param);
	}

	/**
	 * convert OGNL expression to php code
	 * @param string $expr : OGNL 표현식
	 * @return string
	 */
	public static function parse($expr) {
		$result = self::getExprCache($expr);
		if (is_null($result)) {
			$scopeIndex = [];
			$code = [];
			preg_match_all('/(' .
				// literal starts with double quote
				'"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|' .
				// literal starts with single quote
				'\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|' .
				// numbers
				'\d(?:\.[\d]+)?|' .
				// (, )
				'[\(\)]|' .
				// [, ]
				'[\[\]]|' .
				// property, method, function
				'[.]*[\w]+([(])?|' .
				// operators
				'[\^\/*%+\-!=<>,.?:|~]{1,3}|' .
				// $ sign
				'\$' .
				')/', $expr, $matches);
		
			$last = self::NONE;

			for ($i = 0, $l = count($matches[0]); $i < $l; $i++) {
				$token1 = $matches[0][$i];
				$token2 = $matches[1][$i];
				$token3 = $matches[2][$i];
				
				switch (strtolower($token2)) {
					case '++':
					case '+=':
					case '--':
					case '-=':
					case '*=':
					case '/=':
					case '~=':
					case '.=':
					case '=':
						throw new \Exception('assignment operator not allowed');
					case '$':
						throw new \Exception('$ sign not allowed');
					case 'and':
						$code[] = ' && ';
						break;
					case 'or':
						$code[] = ' || ';
						break;
					case 'eq':
						$code[] = '===';
						break;
					case 'lt':
						$code[] = '<';
						break;
					case 'gt':
						$code[] = '>';
						break;
					case 'lte':
						$code[] = '<=';
						break;
					case 'gte':
						$code[] = '>=';
						break;
					case '^':
					case '.':
					case '|':
					case '||':
					case '?:':
					case '!=':
					case '!==':
					case '==':
					case '===':
					case '<=':
					case '>=':
					case '>':
					case '<':
					case '<>':
					case '<<':
					case '>>':
					case '~':
					case '+':
					case '-':
					case '*':
					case '/':
					case '%':
					case ',':
					case '?':
					case ':':
					case '::':
					case '!':
						$code[] = $token2;
						$last = self::OPERATOR;
						break;
					case ')':
						$code[] = $token2;
						$last = self::METHOD_END;
						break;
					case 'null':
						$code[] = 'NULL';
						$last = self::NONE;
						break;
					case '(':
						$temp = [];
						$depth = 1;
						while (++$i < $l) {
							$token1 = $matches[0][$i];
							$token2 = $matches[1][$i];
							$token3 = $matches[2][$i];
							if ($token2 == '(') {
								$depth += 1;
							}
							if ($token2 == ')') {
								$depth -= 1;
							}
							if ($depth == 0) {
								break;
							}
							$temp[] = $token2;
						}
						$code[] = '(' . self::parse(implode(' ', $temp)) . ')';
						$last = self::ARRAY_END;
						break;
					case '[':
						$temp = [];
						$depth = 1;
						while (++$i < $l) {
							$token1 = $matches[0][$i];
							$token2 = $matches[1][$i];
							$token3 = $matches[2][$i];
							if ($token2 == '[') {
								$depth += 1;
							}
							if ($token2 == ']') {
								$depth -= 1;
							}
							if ($depth == 0) {
								break;
							}
							$temp[] = $token2;
						}
						$code[] = '[' . self::parse(implode(' ', $temp)) . ']';
						$last = self::ARRAY_END;
						break;
					default:
						//numbers
						if (is_numeric($token2)) {
							$code[] = $token2;
							$last = self::NUM;
						}
						//literal
						else if ($token2[0] == '"' || $token2[0] == "'") {
							$code[] = $token2;
							$last = self::STR;
						}
						//property or method
						else if ($token2[0] == '.') {
							//method
							if ($token3 == '(') {
									$code[] = '->' . substr($token2, 1);
									$last = self::METHOD_START;
							}
							//property
							else {
								if ($last == self::METHOD_END) {
									$idx = array_pop($scopeIndex) ?: 0;
									$code[$idx] = __CLASS__ . '::v(' . $code[$idx];
									$code[] = ',[\'' . substr($token2, 1) . '\'';
								} else if ($last == self::ARRAY_END) {
									$idx = array_pop($scopeIndex) ?: 0;
									$code[$idx] = __CLASS__ . '::v(' . $code[$idx];
									$code[] = ',[\'' . substr($token2, 1) . '\'';
								} else {
									array_pop($code);
									$code[] = ',\'' . substr($token2, 1) . '\'';
								}
								$code[] = '])';
								$last = self::PROP;
							}
						}
						//function or method
						else if ($token3 == '(') {
							$token2 = trim(substr($token2, 0, -1));
							//call global function when match with self::$globalFunctionPattern
							if (preg_match(self::$globalFunctionPattern, $token2)) {
								$code[] = $token2 . '(';
							}
							//method
							else {
								$code[] = '$param->' . $token2 . '(';
							}
							$last = self::METHOD_START;
						}
						//constants
						else if (defined($token2)) {
							$code[] = $token2;
							$last = self::CONSTANT;
						}
						//start of property chain
						else {
							$scopeIndex[] = count($code);
							$code[] = __CLASS__ . '::v($param,[\'' . $token2 . '\'';
							$code[] = '])';
							$last = self::START;
						}
						break;
				}
			}
			//fix "0==NULL"
			$result = preg_replace('/([!=])=NULL/', '\1==NULL', implode('', $code));
			self::setExprCache($expr, $result);
		}
		return $result;
	}
}