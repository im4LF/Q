<?php
/**
 * @author im4LF
 * @version 0.0.3
 */

define('Q_PATH', realpath(dirname(__FILE__)));

$__qr = array();
$__q_segmentation_func = null;
$__qds = array();

function __Q_parseQuery($sql, &$values)
{
	global $__qr, $__q_segmentation_func;
	
	$alias = 'default';
	if (preg_match('/^([0-9a-z\-_\*]+?)\:\s*/', $sql, $matches, PREG_OFFSET_CAPTURE))
	{
		$alias = $matches[1][0];
		$sql = substr($sql, strlen($matches[0][0]));

		if ('*' == $alias && !is_null($__q_segmentation_func))
			$alias = call_user_func_array($__q_segmentation_func, array($values));
		else
			$alias = 'default';
	}
	
	if (!array_key_exists($alias, $__qr))
		throw new QException('DB alias ['.$alias.'] not defined');
		
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
	$buf = __Q_parseQuery($sql, $values);
	if (!$buf['sql'])
		return $__qr[$buf['alias']];
		
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
	$buf = __Q_parseQuery($sql, $values);
	
	return $__qr[$buf['alias']]->buildQuery($buf['sql'], $values);
}

/**
 * Database objects factory
 * 
 * @param string $dsn
 * @return object
 */
function QF($dsn, $segmentation_func = null)
{
	global $__q_segmentation_func;
	
	$__q_segmentation_func = $segmentation_func;
	
	$config = @parse_url($dsn);
	if (isset($config['query']))
	{
		$buf = explode('&', $config['query']);
		foreach ($buf as $param)
		{
			list ($k, $v) = explode('=', $param);
			$config['params'][$k] = $v;
		}
		unset($config['query']);
	}
	
	$package_name = 'Q'.ucfirst($config['scheme']);
	$class_name = $package_name.'_Driver';
	
	if (!class_exists($class_name))
	{
		$package_file = Q_PATH.DIRECTORY_SEPARATOR.$package_name.'.package.php';	
		if (!file_exists($package_file))
			throw new QException('Package ['.$package_name.'] not found ('.$package_file.')');
		
		require $package_file;
	}
	
	$object = new $class_name($config); 
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
	protected $_table_prefix;
	
	protected $_table_segmentation_func;
	
	/**
	 * Set alias for connection and save it in registry 
	 * 
	 * @param string $alias [optional]
	 * @return object
	 */
	function alias($alias = null)
	{
		global $__qr;
		
		if (is_null($alias))
			return $this->_alias;
			
		$this->_alias = $alias;
		$__qr[$alias] = $this;
		return $this;
	}
	
	function tablePrefix($prefix = null)
	{
		if (is_null($prefix))
			return $this->_table_prefix;
			
		$this->_table_prefix = (array) $prefix;
		return $this;
	}
	
	function tableSegmentationFunc($name = null)
	{
		if (is_null($name))
			return $this->_table_segmentation_func;
			
		$this->_table_segmentation_func = $name;
		return $this;
	}
	
	function benchmark($mode = null)
	{
		if (is_null($mode))
			return $this->_benchmark;
			
		$this->_benchmark = $mode;
		return $this;
	}
}

class QException extends Exception
{
	function __construct($message, $code = 0) 
	{
		parent::__construct($message, $code);
    }
}
