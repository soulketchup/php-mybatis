<?php

namespace Ketchup\Web\Util;

class Cast {

    /**
     * $_GET or $_POST array to object
     *
     * @param string $class_name
     * @param array $array
     * @param string $key_prefix
     * @return object
     */
    public static function toSingle($class_name, &$array, $key_prefix = '') {
        $item = new $class_name;
        foreach (get_class_methods($class_name) as $method) {
            if (strlen($method) > 3 && substr($method, 0, 3) == 'set') {
                $k = substr($method, 3);
                if (isset($array[$key_repfix . $k])) {
                    $item->{$method}($array[$key_prefix . $k]);
                }
            }
        }
        foreach (get_object_vars($item) as $k => $v) {
            if (isset($array[$key_prefix . $k])) {
                $item->{$k} = self::cast($array[$key_prefix . $k], $v);
            }
        }
        return $item;
    }

    /**
     * $_GET or $_POST array to object array
     *
     * @param string $class_name
     * @param array $array
     * @param integer $count_limit
     * @param string $key_prefix
     * @return object[]
     */
    public static function toList($class_name, &$array, $count_limit = 0, $key_prefix = '') {
        $props = [];
        $methods = [];
        $list = [];
        $len = 0;
        foreach (get_class_methods($class_name) as $method) {
            if (strlen($method) > 3 && substr($method, 0, 3) == 'set') {
                $k = substr($method, 3);
                if (isset($array[$key_prefix . $k]) && is_array($array[$key_prefix . $k])) {
                    $methods[$k] = $method;
                    $len = max($len, count($array[$key_prefix . $k]));
                }
            }
        }
        foreach (get_class_vars($class_name) as $k => $v) {
            if (isset($array[$key_prefix . $k]) && is_array($array[$key_prefix . $k])) {
                $props[$k] = $v;
                $len = max($len, count($array[$key_prefix . $k]));
            }
        }
        if ($count_limit > 0) {
            $len = min($count_limit, $len);
        }
        for ($i = 0; $i < $len; $i++) {
            $item = new $class_name;
            foreach ($methods as $k => $v) {
                if (isset($array[$key_prefix . $k][$i])) {
                    $item->{$v}($array[$key_prefix . $k][$i]);
                }
            }
            foreach ($props as $k => $v) {
                if (isset($array[$key_prefix . $k][$i])) {
                    $item->{$k} = self::cast($array[$key_prefix . $k][$i], $v);
                }
            }
            $list[] = $item;
        }
        return $list;
    }

    /**
     * safe type casting
     *
     * @param string $input
     * @param mixed $default
     * @return mixed
     */
    public static function cast($input, $default = NULL) {
        $value = $input;
        if (is_null($default)) {
            return $value;
        }
        else if (is_bool($default)) {
            if (preg_match('/^(0|false)$/i', $value)) {
                return FALSE;
            }
            if (preg_match('/^(1|true)$/i', $value)) {
                return TRUE;
            }
            return $default;
        }
        else if (is_int($default)) {
            if (preg_match('/^[+-]?[0-9]{1,3}(?:,?[0-9]{3})*$/', $value)) {
                return intval(preg_replace('/,/', '', $value));
            }
            return $default;
        }
        else if (is_float($default)) {
            if (preg_match('/^[+-]?[0-9]{1,3}(?:,?[0-9]{3})*(?:\.[0-9]+)?$/', $value)) {
                return floatval(preg_replace('/,/', '', $value));
            }
            return $default;
        }
        return $value;
    }
}