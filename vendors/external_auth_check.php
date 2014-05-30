<?php

class Dummy {
    function __get($key) {

    }

    function __set($key, $value) {

    }

    function __call($name, $args) {

    }

    static function __callStatic($name, $args) {

    }
}

class Cache extends Dummy {
    static function config($key, $value) {

    }
}

class Configure {
    static $data = array();

    static function write($key, $value) {
        self::$data[$key] = $value;
    }

    static function read($key) {
        return self::$data[$key];
    }
}

include_once($API_DIRECTORY . 'app/Config/core.php');
include_once($API_DIRECTORY . 'app/Config/database.php');

class DataSource extends Dummy {
    static public $classMap = array(
        'Auth'       => 'auth',
        'User'       => 'users',
        'Country'    => 'countries',
        'City'       => 'cities',
        'UserRating' => 'user_ratings',
        'Credo'      => 'credo',
    );

    function fullTableName($object) {
        $class = get_class($object);

        return self::$classMap[$class];
    }
}

class AppModel extends Dummy {
    private $config;
    private $connection;

    function __construct() {
        $conf         = new DATABASE_CONFIG();
        $this->config = $conf->default;

        $dsn     = "mysql:host={$this->config['host']};dbname={$this->config['database']}";
        $login   = $this->config['login'];
        $pass    = $this->config['password'];
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
        );

        $this->connection = new PDO($dsn, $login, $pass, $options);
    }

    private function detectOperatorInWhereKey($key) {
        $ls = substr($key, -1);

        if (in_array($ls, array('<', '>', '='))) {
            return '';
        } else {
            return '=';
        }
    }

    private function recursiveWhere($where, &$binds, $type = 'AND') {
        $res = array();
        foreach ($where as $k => $v) {
            if ($k == (string)((int) $k)) {
                if (is_array($v)) {
                    foreach ($v as $k1 => $v1) {
                        $op = $this->detectOperatorInWhereKey($k1);
                        $res[] = "{$k1} {$op} ?";
                        $binds[] = $v1;
                    }
                } else {
                    $res[] = $v;
                }
            } else {
                if ($k == 'AND' || $k == 'OR') {
                    $r = $this->recursiveWhere($v, $binds, $k);
                } elseif (is_numeric($k)) {
                    $r = $v;
                } else {
                    $op = $this->detectOperatorInWhereKey($k);
                    $r = "{$k} {$op} ?";
                    $binds[] = $v;
                }
                $res[] = $r;
            }
        }

        return '(' . implode(") {$type} (", (array) $res) . ')';
    }

    function find($type, $options) {
        if (isset($options['conditions'])) {
            $conds = $options['conditions'];
        } else {
            $conds = array();
        }
        if (isset($options['order'])) {
            $ord = $options['order'];
        } else {
            $ord = array();
        }
        if (isset($options['fields'])) {
            $fields = (array) $options['fields'];
        } else {
            $fields = array('*');
        }
        if (isset($options['joins'])) {
            $joins = $options['joins'];
        } else {
            $joins = array();
        }

        $binds = array();

        $res_joins = '';
        $models = array();
        foreach ($joins as $join) {
            if (is_array($join['conditions'])) {
                $join_str = $this->recursiveWhere($join['conditions'], $binds);
            } else {
                $join_str = $join['conditions'];
            }
            $res_joins .= " {$join['type']} JOIN {$join['table']} as {$join['alias']} ON (" . $join_str . ')';
            $models[] = $join['alias'];
        }

        $alias = get_class($this);
        $table = $this->getDataSource()->fullTableName($this);

        $models[] = $alias;

        $result_map = array();
        foreach ($fields as &$field) {
            foreach ($models as $model) {
                $matches = array();
                $str = preg_match('/\b' . $model . '\.(\w+).*$/', $field, $matches);
                $str2 = preg_match('/as\s+\w+$/', $field);
                if ($str && !$str2) {
                    $field_name = $model . '_' . $matches[1];
                    $result_map[$field_name] = array('model' => $model,
                                                     'name'  => $matches[1]);
                    $field .= " as {$field_name}";
                } elseif (preg_match('/^\w+$/', $field)) {
                    $field_name = $alias . '_' . $field;
                    $result_map[$field_name] = array('model' => $alias,
                                                     'name'  => $field);
                    $field .= " as {$field_name}";
                }
            }
        }

        $res_fields = implode(',', (array)$fields);

        $res_cond = $this->recursiveWhere($conds, $binds);

        $res_ord  = implode(',', $ord);

        $sql = "SELECT {$res_fields}
                  FROM {$table} as {$alias}
                 {$res_joins}";

        if ($conds) {
            $sql .= " WHERE {$res_cond}";
        }
        if ($ord) {
            $sql .= " ORDER BY {$res_ord}";
        }

        if ($type == 'first') {
            $sql .= ' LIMIT 1';
        }

//        echo '<pre>';
//        print_r($sql);
//        echo '</pre><br><br>';
//        echo '<pre>';
//        print_r($binds);
//        echo '</pre><br><br>';

        $query = $this->connection->prepare($sql);
        if ($query->execute($binds)) {
            if ($type == 'first') {
                $res = $query->fetch(PDO::FETCH_ASSOC);
            } else {
                $res = $query->fetchAll(PDO::FETCH_ASSOC);
            }

            $result = array();
            if (is_array($res) && count($res) > 0) {
                $keys = array_keys($res);
                if (is_array($res[$keys[0]])) {
                    foreach ($res as $row) {
                        $r = array();
                        foreach ($row as $k => $v) {
                            if (isset($result_map[$k])) {
                                $r[$result_map[$k]['model']][$result_map[$k]['name']] = $v;
                            } else {
                                $r[0][$k] = $v;
                            }
                        }
                        $result[] = $r;
                    }
                } else {
                    foreach ($res as $k => $v) {
                        if (isset($result_map[$k])) {
                            $result[$result_map[$k]['model']][$result_map[$k]['name']] = $v;
                        } else {
                            $result[0][$k] = $v;
                        }
                    }
                }
            }

            return $result;
        } else {
            return false;
        }
    }

    function getDataSource() {
        return new DataSource();
    }
}

include_once($API_DIRECTORY . 'app/Model/Auth.php');

class App extends Dummy {
    static function uses($model, $class) {

    }
}

class Request extends Dummy {
    public $query = array();
}

class Controller extends Dummy {
    public $Auth;
    public $request;

    function __construct() {
        $this->Auth    = new Auth();
        $this->request = new Request();

        if (isset($_GET['user_id'])) {
            $this->request->query['user_id'] = $_GET['user_id'];
        }
    }
}

include_once($API_DIRECTORY . 'app/Controller/AppController.php');

$controller = new AppController();
$controller->beforeFilter();

global $user_id;
$user_id = $controller->currentUserId;