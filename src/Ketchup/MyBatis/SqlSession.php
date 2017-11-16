<?php

namespace Ketchup\MyBatis;

class SqlSession {

    /** @var \PDO $db */
     private $db;

    /**
     * create PDO instance with given params
     * array('url' => '', 'username' => '', 'password' => '')
     * @param array $config
     */
    public function __construct($config) {
        $this->db = new \PDO($config['url'], $config['username'], $config['password']);
        $this->db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, TRUE);
        $this->db->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, FALSE);
    }

    /**
     * unset PDO instance
     *
     * @return void
     */
    public function destroy() {
        $this->db = NULL;
    }

    /**
     * throw error
     *
     * @param \PDOStatement $stmt
     * @return void
     */
    private function throwError($stmt) {
        $err = print_r($stmt->errorInfo(), TRUE);
        throw new \Exception($err);
    }

    /**
     * return PDOStatement
     *
     * @param string $query
     * @param array|NULL $params
     * @return \PDOStatement
     */
    public function getStmt($query, $params = NULL) {
        $stmt = $this->db->prepare($query); /** @var \PDOStatement $stmt */
        if ($stmt) {
            if (is_array($params)) {
                foreach ($params as $k => $v) {
                    if (is_int($v)) {
                        $stmt->bindValue($k, $v, \PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue($k, $v);
                    }
                }
            }
        } else {
            $err = print_r($this->db->errorInfo(), TRUE);
            throw new \Exception($err);
        }
        return $stmt;
    }

    /**
     * return single value
     *
     * @param string $query
     * @param array|NULL $params
     * @return mixed|NULL
     */
    public function queryValue($query, $params = NULL) {
        $stmt = $this->getStmt($query, $params);
        if ($stmt->execute()) {
            return $stmt->fetchColumn();
        } else {
            $this->throwError($stmt);
        }
        return NULL;
    }

    /**
     * return rows array
     *
     * @param string $query
     * @param array|NULL $params
     * @return array
     */
    public function query($query, $params = NULL) {
        $stmt = $this->getStmt($query, $params);
        if ($stmt->execute()) {
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $this->throwError($stmt);
        }
    }

    /**
     * return single row array
     *
     * @param string $query
     * @param array|NULL $params
     * @return array|NULL
     */
    public function querySingle($query, $params = NULL) {
        $stmt = $this->getStmt($query, $params);
        $result = FALSE;
        if ($stmt->execute()) {
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        } else {
            $this->throwError($stmt);
        }
        return $result === FALSE ? NULL : $result;
    }

    /**
     * return object array
     *
     * @param string $class : class name
     * @param string $query
     * @param array|NULL $params
     * @return object[]
     */
    public function queryAs($class, $query, $params = NULL) {
        $stmt = $this->getStmt($query, $params);
        $result = [];
        if ($stmt->execute()) {
            while ($row = $stmt->fetchObject($class)) {
                $result[] = $row;
            }
        } else {
            $this->throwError($stmt);
        }
        return $result;
    }

    /**
     * return object
     *
     * @param string $class : class name
     * @param string $query
     * @param array|NULL $params
     * @return object|NULL
     */
    public function querySingleAs($class, $query, $params = NULL) {
        $stmt = $this->getStmt($query, $params);
        $result = FALSE;
        if ($stmt->execute()) {
            $result = $stmt->fetchObject($class);
        } else {
            $this->throwError($stmt);
        }
        return $result === FALSE ? NULL : $result;
    }

    /**
     * execute query with given parameter array
     *
     * @param string $query
     * @param array|NULL $params
     * @return bool
     */
    public function execute($query, $params = NULL) {
        $stmt = $this->getStmt($query, $params);
        if ($stmt->execute()) {
            return TRUE;
        } else {
            $this->throwError($stmt);
        }
        return FALSE;
    }

    /**
     * return last insert id
     *
     * @return mixed
     */
    public function getLastInsertId() {
        return $this->db->lastInsertId();
    }
}