<?php

class Qmysqli_Driver extends QAny_Driver
{
    protected $_link;
    protected $_fetch_mode;
    protected $_query_mode;

    public function __construct($config)
    {
        $this->_config = $config;
        $this->_config['path'] = substr($this->_config['path'], 1);
        $this->fetchMode('assoc');
    }

    protected function _throwException()
    {
        if (is_resource($this->_link)) {
            $code     = $this->_link->connect_errno;
            $message = $this->_link->connect_error;
        } else {
            $code     = $this->_link->connect_errno;
            $message = $this->_link->connect_error;
        }

        throw new QException('Action: '.$this->_action."\n".$message, $code);
    }

    protected function _formatValue($value, $type)
    {
        if ('x' == $type) {
            if (is_null($value)) {
                return 'NULL';
            }
            if (is_int($value)) {
                $type = 'i';
            }
            if (is_float($value)) {
                $type = 'f';
            }
            if (is_bool($value)) {
                $type = 'b';
            }
        }

        switch ($type) {
            case 'li':
                if (!is_array($value) || !count($value)) {
                    return '0';
                }

                $buf = (int) array_shift($value);
                while ($i = array_shift($value)) {
                    $buf .= ',' . (int) $i;
                }

                return $buf;
            case 'ls':
                if (!is_array($value) || !count($value)) {
                    return '\'\'';
                }

                $buf = '\''.$this->_link->escape_string(array_shift($value)).'\'';
                while ($s = array_shift($value)) {
                    $buf .= ',\''.$this->_link->escape_string($s).'\'';
                }

                return $buf;
            case 'e':
                return $value;
            case 'i':
                return (int) $value;
            case 'f':
                return '\''.str_replace(',', '.', (float) str_replace(',', '.', $value)).'\'';
            case 'b':
                return $value ? 1 : 0;
            case 'd':
                return date('\'Y-m-d\'', $value);
            case 't':
                return date('\'H:i:s\'', $value);
            case 'dt':
                return date('\'Y-m-d H:i:s\'', $value);
            default:
                return '\''.$this->_link->escape_string($value).'\'';
        }
    }

    public function connect()
    {
        $this->_action = 'connect';

        if (false === ($this->_link = new mysqli($this->_config['host'], $this->_config['user'], $this->_config['pass'], $this->_config['path']))) {
            $this->_throwException();
        }

        if (isset($this->_config['params']['encoding'])) {
            if (isset($this->_config['params']['encoding'])) {
                $this->_action = 'set encoding';

                if (!$this->_link->set_charset($this->_config['params']['encoding'])) {
                    $this->_throwException();
                }
            }
        }

        return $this;
    }

    public function disconnect()
    {
        $this->_action = 'disconnect';
        if (false === $this->_link->close()) {
            $this->_throwException();
        }

        return $this;
    }

    public function fetchMode($mode = null)
    {
        if (!$mode) {
            return $this->_fetch_mode;
        }

        $this->_fetch_mode = $mode;
        return $this;
    }

    public function buildQuery($sql, $values)
    {
        // detect query mode
        $this->_query_mode = 'select';

        if (preg_match('/^\s*(\w+)/i', $sql, $matches)) {
            $this->_query_mode = strtolower($matches[1]);
        }

        // replace # by table prefix
        if (false !== strpos($sql, '#_')) {
            $this->_action = 'build query, table prefixes: ['.$sql.']';

            $buf = explode('#_', $sql);
            for ($i = 1; $i < count($buf); $i++) {
                $prefix_id = 0;
                $prefix_id_len = 0;
                if (preg_match('/^(\d+)/', $buf[$i], $matches)) {
                    $prefix_id = $matches[1];
                    $prefix_id_len = strlen($matches[1]);
                }

                if (!isset($this->_table_prefix[$prefix_id])) {
                    return false;
                }

                $buf[$i] = $this->_table_prefix[$prefix_id].'_'.substr($buf[$i], $prefix_id_len);
            }
            $sql = implode('', $buf);
        }

        if (is_null($values)) {    // if values not passed - return builded sql
            return $sql;
        }

        $this->_action = 'build query, place holders: ['.$sql.']';

        $aliases = [];

        // replace values for insert mode
        if ('insert' == $this->_query_mode) {
            $insert_set = false;
            //for ($i = 0, $in = count($values); $i < $in; $i++)
            foreach ($values as $value) {
                //if (!is_array($values[$i]))
                if (!is_array($value)) {
                    continue;
                }

                $insert_set = true;
                break;
            }

            if ($insert_set) {
                // find template for inserting set of data, match inner blocks - (some block (inner block))
                if (!preg_match('/\((?>[^)(]+|(?R))*\)/x', $sql, $template, PREG_OFFSET_CAPTURE, stripos($sql, 'VALUES'))) {
                    return false;
                }

                //__($template);

                //$buf = explode('?', $template[1][0]);
                $buf = explode('?', substr($template[0][0], 1, strlen($template[0][0])-2));
                $matches = [];
                for ($i = 1; $i < count($buf); $i++) {
                    if (!preg_match('/^(\w+)(:(\w+))?/', $buf[$i], $matches)) {
                        continue;
                    }

                    $buf[$i] = array(
                        'type' => $matches[1],
                        'alias' => isset($matches[3]) ? $matches[3] : null,
                        'after' => substr($buf[$i], strlen($matches[1]) + (isset($matches[2]) ? strlen($matches[2]) : 0))
                    );
                }

                //__($buf);
                $set = [];
                //foreach ($values as $k => &$row)
                $i = -1;
                $n = count($values)-1;
                $value = null;
                //while ($i < $n)
                foreach ($values as $key => &$row) {
                    //if (!is_array($values[$i]))
                    if (!is_array($row)) {
                        continue;
                    }

                    $i++;

                    $set[$i] = '('.$buf[0];
                    for ($j = 1; $j < count($buf); $j++) {
                        if (!is_null($buf[$j]['alias'])) {    // if is set alias of field
                            //$current_alias = $i.'-'.$buf[$j]['alias'];
                            $current_alias = $buf[$j]['alias'];
                            if (!@array_key_exists($current_alias, $aliases[$i])) {
                                $aliases[$i][$current_alias] = $row[$buf[$j]['alias']];
                                unset($row[$buf[$j]['alias']]);
                            }

                            $value = $aliases[$i][$current_alias];
                        } else {
                            $value = array_shift($row);
                        }

                        $set[$i] .= $this->_formatValue($value, $buf[$j]['type']).$buf[$j]['after'];
                    }

                    $set[$i] .= ')';
                    unset($values[$key]);
                    //unset($aliases[$i]);
                }

                $sql = substr($sql, 0, $template[0][1]).implode(', ', $set).substr($sql, $template[0][1]+strlen($template[0][0]));
            }
        }
        //__($values);
        // replace place holders by values
        //if (false !== strpos($sql, '?'))  // if values not a set of array
        if (count($values)) {
            $buf = explode('?', $sql);
            $matches = [];
            $value = null;

            for ($i = 1; $i < count($buf); $i++) {
                if (!preg_match('/^(\w+)(:(\w+))?/', $buf[$i], $matches)) {
                    continue;
                }

                if (isset($matches[3])) {    // if is set alias of field
                    //if (!array_key_exists($matches[3], $aliases) && array_key_exists($matches[3], $values))
                    if (!array_key_exists($matches[3], $aliases)) {
                        $aliases[$matches[3]] = $values[$matches[3]];
                        unset($values[$matches[3]]);
                    }
                    /*else
                        $aliases[$matches[3]] = array_shift($values);*/

                    $value = $aliases[$matches[3]];
                } else {
                    $value = array_shift($values);
                }

                $buf[$i] = $this->_formatValue($value, $matches[1]).
                            substr($buf[$i], strlen($matches[1]) + (isset($matches[2]) ? strlen($matches[2]) : 0));
            }
            $sql = implode('', $buf);
        }
        //__($aliases);
        return $sql;
    }

    public function query($sql, $values = [])
    {
        global $__qds;

        if (false === ($sql = $this->buildQuery($sql, $values))) {
            $this->_throwException();
        }

        $t0 = microtime(true);
        $this->_action = 'execute query: ['.$sql.']';

        if (false === ($res = $this->_link->query($sql))) {
            $this->_throwException();
        }

        $t1 = microtime(true);
        $__qds[$this->_action] = $t1-$t0;

        if (defined('DEV_MODE') && DEV_MODE && isset($_SESSION['sql_log']))
        {
            $_SESSION['sql_log'][] = $__qds;
        }

        switch ($this->_query_mode) {
            case 'insert':
                return $this->_link->insert_id;
            break;

            case 'update': case 'delete': case 'replace':
                return $this->_link->affected_rows;
            break;
        }

        if (defined('DEV_MODE') && DEV_MODE && isset($_SESSION['sql']))
        {
            $_SESSION['sql'] += 1;
        }

        return new Qmysqli_Result($res, $this->_fetch_mode);
    }
}

class Qmysqli_Result
{
    protected $_result;
    protected $_fetch_mode;
    protected $_fetch_function;

    public function __construct($result, $fetch_mode = 'assoc')
    {
        $this->_result = $result;
        $this->fetchMode($fetch_mode);
    }

    public function fetchMode($mode = null)
    {
        if (!$mode) {
            return $this->_fetch_mode;
        }

        $this->_fetch_mode = $mode;
        $this->_fetch_function = 'fetch_'.$mode;
        return $this;
    }

    public function numRows()
    {
        return $this->_result->num_rows;
    }

    public function fetchRow($field = null)
    {
        return $this->row($field);
    }

    public function row($field = null)
    {
        $fetch_function = $this->_fetch_function;

        $buf = $this->_result->{$fetch_function}();
        $this->_result->free();

        if (false === $buf) {
            return false;
        }

        if (null !== $field && (isset($buf[$field]) || (is_array($buf) && array_key_exists($field, $buf)))) {
            return $buf[$field];
        }

        return $buf;
    }

    public function fetchEach()
    {
        return $this->each();
    }

    public function each()
    {
        $fetch_function = $this->_fetch_function;

        if (false === ($buf = $this->_result->{$fetch_function}())) {
            $this->_result->free();
            return false;
        }

        return $buf;
    }

    public function fetchAll($by_key = null)
    {
        return $this->all($by_key);
    }

    public function all($by_key = null)
    {
        $all = [];
        $fetch_function = $this->_fetch_function;

        if (!is_null($this->_result)) {
            while ($row = $this->_result->{$fetch_function}()) {
                if (null !== $by_key) {
                    $all[$row[$by_key]] = $row;
                } else {
                    $all[] = $row;
                }
            }

            $this->_result->free();
        }

        return $all;
    }

    public function free()
    {
        $this->_result->free();
        return $this;
    }
}
