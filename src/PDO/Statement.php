<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\PDO;

use KoolKode\Database\DB;
use KoolKode\Database\LargeObjectStream;
use KoolKode\Database\ParamEncoderInterface;
use KoolKode\Database\PlaceholderList;
use KoolKode\Database\StatementInterface;

/**
 * Adapts a wrapped PDO statement to the KoolKode Database API.
 * 
 * @author Martin Schröder
 */
class Statement implements StatementInterface
{
	/**
	 * The database connection instance.
	 * 
	 * @var Connection
	 */
	protected $conn;
	
	/**
	 * The SQL query string without database-specific LIMIT / OFFSET clause.
	 * 
	 * @var string
	 */
	protected $sql;
	
	/**
	 * The wrapped PDO statement.
	 * 
	 * @var \PDOStatement
	 */
	protected $stmt;
	
	protected $limit = 0;
	
	protected $offset = 0;
	
	protected $params = [];
	
	protected $encoders = [];
	
	protected $enhanced = false;
	
	protected $transformed = [];
	
	protected $computed = [];
	
	public function __construct(Connection $conn, $sql)
	{
		$this->conn = $conn;
		$this->sql = (string)$sql;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function bindValue($param, $value)
	{
		$this->params[$param] = $value;
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function bindAll(array $params)
	{
		foreach($params as $k => $v)
		{
			$this->bindValue($k, $v);
		}
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function bindList(PlaceholderList $list)
	{
		return $this->bindAll($list->getBindParams());
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function setLimit($limit)
	{
		$limit = (int)$limit;
		
		if($limit !== $this->limit)
		{
			$this->closeCursor();
			$this->stmt = NULL;
		}
		
		$this->limit = $limit;
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function setOffset($offset)
	{
		$offset = (int)$offset;
		
		if($offset !== $this->offset)
		{
			$this->closeCursor();
			$this->stmt = NULL;
		}
		
		$this->offset = $offset;
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute()
	{
		if($this->stmt === NULL)
		{
			$sql = $this->sql;
			
			if($this->limit > 0)
			{
				// TODO: Need more specific limit / offset support especially related to SQL 2008 standard.
				$version = $this->conn->getServerVersion();
				
				switch($this->conn->getDriverName())
				{
					case DB::DRIVER_SQLITE:
					case DB::DRIVER_MYSQL:
					case DB::DRIVER_POSTGRESQL:
						$sql .= sprintf(' LIMIT %u OFFSET %u', $this->limit, $this->offset);
						break;
					case DB::DRIVER_CUBRID:
						$sql .= sprintf(' LIMIT %u, %u', $this->offset, $this->limit);
						break;
					case DB::DRIVER_ORACLE:
						
						$num = 0;
						
						if(preg_match("'([0-9]+)[cg]'i", $version, $m))
						{
							$num = (int)$m[1];
						}
						if($num >= 12)
						{
							$sql .= sprintf(' OFFSET %u ROWS FETCH NEXT %u ROWS ONLY', $this->offset, $this->limit);
						}
						else
						{
							$sql = sprintf(
								'SELECT * FROM (SELECT kklq.*, ROWNUM kkrn FROM (' . $sql . ') kklq WHERE ROWNUM <= %u) WHERE kkrn > %u',
								$this->offset + $this->limit,
								$this->limit
							);
						}
						
						break;
					case DB::DRIVER_DB2:
						if($this->offset === 0)
						{
							$sql .= sprintf(' FETCH FIRST %u ROWS ONLY', $this->limit);
						}
						else
						{
							throw new \RuntimeException(sprintf('Limit + offset query not implemented for DB2'));
						}
						break;
					default:
						throw new \RuntimeException(sprintf('Limit / Ofsset support not implemented for driver "%s"', $this->conn->getDriverName()));
				}
			}
			
			$this->stmt = $this->conn->getPDO()->prepare($sql);
			$this->stmt->setFetchMode(\PDO::FETCH_ASSOC);
		}
		else
		{
			$this->closeCursor();
		}
		
		foreach($this->params as $k => $v)
		{
			$encoded = false;
			
			foreach($this->encoders as $encoder)
			{
				$result = $encoder->encodeParam($this->conn, $v, $encoded);
				
				if($encoded)
				{
					$v = $result;
					
					break;
				}
			}
			
			if($v instanceof LargeObjectStream)
			{
				$this->stmt->bindValue($k, $v->getResource(), \PDO::PARAM_LOB);
			}
			else
			{
				$this->stmt->bindValue($k, $v);
			}
		}
		
		$start = microtime(true);
		$this->stmt->execute();
		$time = microtime(true) - $start;
		
		return (int)$this->stmt->rowCount();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function closeCursor()
	{
		while(true)
		{
			$this->stmt->closeCursor();
			
			try
			{
				if($this->stmt->nextRowset())
				{
					continue;
				}
			}
			catch(\Exception $e) { }
			
			break;
		}
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function registerParamEncoder(ParamEncoderInterface $encoder)
	{
		$this->encoders[] = $encoder;
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function transform($column, callable $transformation)
	{
		$this->transformed[$column][] = $transformation;
		$this->enhanced = true;
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function compute($column, callable $callback)
	{
		$this->computed[$column] = $callback;
		$this->enhanced = true;
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function fetchNextRow($style = NULL)
	{
		switch($style)
		{
			case DB::FETCH_ASSOC:
				$style = \PDO::FETCH_ASSOC;
				break;
			case DB::FETCH_BOTH:
				$style = \PDO::FETCH_BOTH;
				break;
			case DB::FETCH_NUM:
				$style = \PDO::FETCH_NUM;
				break;
		}
		
		return $this->enhanced ? $this->transformRow($this->stmt->fetch($style)) : $this->stmt->fetch($style);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function fetchNextColumn($column)
	{
		$transform = (!empty($this->computed[$column]) || !empty($this->transformed[$column]));
		
		if(!$transform && is_integer($column))
		{
			return $this->stmt->fetchColumn($column);
		}
		
		$row = $this->stmt->fetch();
		
		if($row === false)
		{
			return false;
		}
		
		$result = $row[$column];
		
		if(!empty($this->transformed[$column]))
		{
			foreach($this->transformed[$column] as $callback)
			{
				$result = $callback($result);
			}
		}
		
		if(isset($this->computed[$column]))
		{
			$result = call_user_func($this->computed[$column], $row);
		}
		
		return $result;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function fetchRows($style = NULL)
	{
		switch($style)
		{
			case DB::FETCH_ASSOC:
				$style = \PDO::FETCH_ASSOC;
				break;
			case DB::FETCH_BOTH:
				$style = \PDO::FETCH_BOTH;
				break;
			case DB::FETCH_NUM:
				$style = \PDO::FETCH_NUM;
				break;
		}
		
		if($this->enhanced)
		{
			$result = [];
			
			while(false !== ($row = $this->stmt->fetch($style)))
			{
				$result[] = $this->transformRow($row);
			}
			
			return $result;
		}
		
		return $this->stmt->fetchAll($style);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function fetchColumns($column)
	{
		$transform = (!empty($this->computed[$column]) || !empty($this->transformed[$column]));
		
		if(!$transform && is_integer($column))
		{
			return $this->stmt->fetchAll(\PDO::FETCH_COLUMN, $column);
		}
		
		$result = [];
		
		while(false !== ($row = $this->stmt->fetch()))
		{
			if(!empty($this->transformed[$column]))
			{
				foreach($this->transformed[$column] as $callback)
				{
					$row[$column] = $callback($row[$column]);
				}
			}
			
			if(isset($this->computed[$column]))
			{
				$row[$column] = call_user_func($this->computed[$column], $row);
			}
			
			$result[] = $row[$column];
		}
		
		return $result;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function fetchMap($key, $value)
	{
		if(is_integer($key) && is_integer($value))
		{
			$style = \PDO::FETCH_NUM;
		}
		elseif(is_string($key) && is_string($value))
		{
			$style = \PDO::FETCH_ASSOC;
		}
		else
		{
			$style = \PDO::FETCH_BOTH;
		}
		
		$result = [];
		
		if($this->enhanced)
		{
			while(false !== ($row = $this->transformRow($this->stmt->fetch())))
			{
				$result[$row[$key]] = $row[$value];
			}
		}
		else
		{
			while(false !== ($row = $this->stmt->fetch($style)))
			{
				$result[$row[$key]] = $row[$value];
			}
		}
		
		return $result;
	}
	
	protected function transformRow($row)
	{
		if(!is_array($row))
		{
			return $row;
		}
		
		foreach($row as $k => & $v)
		{
			if(!empty($this->transformed[$k]))
			{
				foreach($this->transformed[$k] as $trans)
				{
					$v = $trans($v);
				}
			}
		}
	
		foreach($this->computed as $k => $callback)
		{
			$row[$k] = $callback($row);
		}
	
		return $row;
	}
}
