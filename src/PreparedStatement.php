<?php

/*
 * This file is part of KoolKode Database.
*
* (c) Martin Schröder <m.schroeder2007@gmail.com>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace KoolKode\Database;

/**
 * PDO statement that performs auto-explain queries when the PDO connection is in debug mode.
 * 
 * @author Martin Schröder
 */
class PreparedStatement extends \PDOStatement
{
	protected $conn;
	protected $logger;
	
	protected $boundParams = [];
	
	protected $enhanced = false;
	protected $transforms = [];
	protected $computedColumns = [];
	
	protected function __construct(Connection $conn)
	{
		$this->conn = $conn;
		$this->logger = $conn->getLogger();
	}
	
	public function bindParam($parameter, & $variable, $type = NULL, $length = NULL, $options = NULL)
	{
		$this->boundParams[$parameter] = & $variable;
		
		if($type === NULL)
		{
			return parent::bindParam($parameter, $variable);
		}
		
		if($length === NULL)
		{
			return parent::bindParam($parameter, $variable, $type);
		}
		
		if($options === NULL)
		{
			return parent::bindParam($parameter, $variable, $type, $length);
		}
		
		return parent::bindParam($parameter, $variable, $type, $length, $options);
	}
	
	public function bindValue($parameter, $value, $type = NULL)
	{		
		$value = $this->conn->encodeParam($value, $type);
		
		$this->boundParams[$parameter] = $value;
		
		if($type === NULL)
		{
			return parent::bindValue($parameter, $value);
		}
		
		return parent::bindValue($parameter, $value, $type);
	}
		
	public function execute($params = NULL)
	{
		if(is_array($params))
		{
			foreach($params as $k => $v)
			{
				$params[$k] = $this->conn->encodeParam($v);
			}
		}
		
		if($this->logger !== NULL && $this->conn->isDebug() && !preg_match("'^\s*(?:EXPLAIN|SELECT)\s+'i", $this->queryString))
		{
			$input = array_merge($this->boundParams, (array)$params);
			
			$this->logger->info('Executing SQL query: <{sql}>', [
				'sql' => trim(preg_replace("'\s+'", ' ', $this->queryString))
			]);
			
			foreach($input as $k => $v)
			{
				$this->logger->debug('Param "{name}" bound to <{value}>', [
					'name' => $k,
					'value' => is_object($v) ? (string)$v : $v
				]);
			}
		}
		
		if($this->logger !== NULL && $this->conn->isDebug() && preg_match("'^\s*SELECT\s+'i", $this->queryString))
		{
			$input = array_merge($this->boundParams, (array)$params);
			
			$this->logger->info('Executing SQL query: <{sql}>', [
				'sql' => trim(preg_replace("'\s+'", ' ', $this->queryString))
			]);
			
			foreach($input as $k => $v)
			{
				$this->logger->debug('Param "{name}" bound to <{value}>', [
					'name' => $k,
					'value' => is_object($v) ? (string)$v : $v
				]);
			}
			
			if($this->conn->isMySQL())
			{
				$stmt = $this->conn->prepare('EXPLAIN ' . $this->queryString);
				$stmt->execute($input);
				
				$i = 0;
				
				foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row)
				{
					if(!array_key_exists('select_type', $row))
					{
						continue;
					}
					
					$info = sprintf('%u. >> %s %s %s', ++$i, $row['select_type'], $row['table'], $row['type']);
					$info .= sprintf(' [%s -> %s] %s', $row['possible_keys'], $row['key'], $row['ref']);
					$info .= sprintf(' %u rows %s', $row['rows'], $row['Extra']);
					
					$this->logger->debug('Explain: <info>', [
						'info' => $info
					]);
				}
			}
			
			$start = microtime(true);
			$result = parent::execute($params);
			$time = microtime(true) - $start;
			
			$this->logger->info('Execution time: {time} ms', [
				'time' => ceil($time / 1000)
			]);
			
			return $result;
		}
		
		if($params === NULL || empty($params))
		{
			return parent::execute();
		}
		
		return parent::execute($params);
	}
	
	public function transform($column, callable $transform)
	{
		$this->transforms[$column][] = $transform;
		$this->enhanced = true;
		
		return $this;
	}
	
	public function compute($column, callable $callback)
	{
		$this->computedColumns[$column] = $callback;
		$this->enhanced = true;
		
		return $this;
	}
	
	protected function transformRow(array $row)
	{
		foreach($row as $k => & $v)
		{
			if(!empty($this->transforms[$k]))
			{
				foreach($this->transforms[$k] as $trans)
				{
					$v = $trans($v);
				}
			}
		}
		
		foreach($this->computedColumns as $k => $callback)
		{
			$row[$k] = $callback($row);
		}
		
		return $row;
	}
	
	public function fetch($mode = NULL, $orientation = NULL, $offset = NULL)
	{
		switch(func_num_args())
		{
			case 0:
				$row = parent::fetch();
				break;
			case 1:
				$row = parent::fetch($mode);
				break;
			case 2:
				$row = parent::fetch($mode, $orientation);
				break;
			default:
				$row = parent::fetch($mode, $orientation, $offset);
		}
		
		if($row === false)
		{
			return false;
		}
		
		return $this->enhanced ? $this->transformRow($row) : $row;
	}
	
	public function fetchAll($mode = NULL, $typeName = NULL, $args = NULL)
	{
		switch(func_num_args())
		{
			case 0:
				$result = parent::fetchAll();
				break;
			case 1:
				$result = parent::fetchAll($mode);
				break;
			case 2:
				$result = parent::fetchAll($mode, $typeName);
				break;
			default:
				$result = parent::fetchAll($mode, $typeName, $args);
		}
		
		if(!$this->enhanced)
		{
			return $result;
		}

		$rows = [];
		
		foreach($result as $row)
		{
			$rows[] = $this->transformRow($row);
		}
		
		return $rows;
	}
	
	/**
	 * Create a map from the given columns, both numeric and associative indexes are supported.
	 * 
	 * @param mixed $indexColumn
	 * @param mixed $valueColumn
	 * @return \stdClass Populated with fetched data, integer index values are eventualy being used as field names.
	 */
	public function populateMap($indexColumn = 0, $valueColumn = 1)
	{
		$result = new \stdClass();
		$fetchMode = \PDO::FETCH_BOTH;
		
		if(is_integer($indexColumn) && is_integer($valueColumn))
		{
			$fetchMode = \PDO::FETCH_NUM;
		}
		elseif(is_string($indexColumn) && is_string($valueColumn))
		{
			$fetchMode = \PDO::FETCH_ASSOC;
		}
		
		while($row = $this->fetch($fetchMode))
		{
			$result->{$row[$indexColumn]} = (string)$row[$valueColumn];
		}
		
		return $result;
	}
}
