<?php
/**
 * @author im4LF
 * @version 0.0.3
 */

define('Q_PATH', realpath(dirname(__FILE__)));

$__qr = array();
$__qds = array();

function __Q_parseQuery($sql)
{
    global $__qr;

    $alias = 'default';
    if (preg_match('/^([0-9a-z\-_]+?)\:\s*/', $sql, $matches, PREG_OFFSET_CAPTURE)) {
        $alias = $matches[1][0];
        $sql = substr($sql, strlen($matches[0][0]));
    }

    if (!array_key_exists($alias, $__qr)) {
        throw new QException('DB alias ['.$alias.'] not defined');
    }

    return array(
        'alias' => $alias,
        'sql' => $sql
    );
}

/**
 * Execute query
 *
 * @param string $sql
 * @param array $values [optional]
 * @return
 */
function Q($sql = null, $values = array())
{
    global $__qr;
    $buf = __Q_parseQuery($sql);

    if (!$buf['sql']) {
        return $__qr[$buf['alias']];
    }

    return $__qr[$buf['alias']]->query($buf['sql'], $values);
}

/**
 * Build query and return it
 *
 * @param string $sql
 * @param array $values [optional]
 * @return string
 */
function Qb($sql, $values = array())
{
    global $__qr;
    $buf = __Q_parseQuery($sql);

    return $__qr[$buf['alias']]->buildQuery($buf['sql'], $values);
}

/**
 * Database objects factory
 *
 * @param string $dsn
 * @return object
 */
function QF($dsn)
{
    $config = parse_url($dsn);
    if (isset($config['query'])) {
        parse_str($config['query'], $config['params']);
        unset($config['query']);
    }

    $package_name = 'Q'.ucfirst($config['scheme']);
    $class_name = $package_name.'_Driver';

    if (!class_exists($class_name)) {
        $package_file = Q_PATH.DIRECTORY_SEPARATOR.$package_name.'.package.php';
        if (!file_exists($package_file)) {
            throw new QException('Package ['.$package_name.'] not found ('.$package_file.')');
        }

        require $package_file;
    }

    $object = new $class_name($config);
    if (isset($config['params']['table_prefix'])) {
        $object->tablePrefix($config['params']['table_prefix']);
    }

    return $object;
}

class QAny_Driver
{
    protected $_alias;
    protected $_config;
    protected $_action;
    protected $_place_holders = array(
        'i' => 'integer',
        'f' => 'float',
        'b' => 'boolean',
        's' => 'string',
        'd' => 'date',
        't' => 'time',
        'dt' => 'datetime',
    );
    protected $_query_mode;
    protected $_table_prefix = array('');

    /**
     * Set alias for connection and save it in registry
     *
     * @param string $alias [optional]
     * @return object
     */
    public function alias($alias = null)
    {
        global $__qr;

        if (is_null($alias)) {
            return $this->_alias;
        }

        $this->_alias = $alias;
        $__qr[$alias] = $this;

        return $this;
    }

    public function tablePrefix($prefix = null)
    {
        if (is_null($prefix)) {
            return $this->_table_prefix;
        }

        $this->_table_prefix = (array) $prefix;
        return $this;
    }

    public function benchmark($mode = null)
    {
        if (is_null($mode)) {
            return $this->_benchmark;
        }

        $this->_benchmark = $mode;
        return $this;
    }
}

class QException extends Exception
{
    public function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }
}
