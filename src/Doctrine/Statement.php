<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Doctrine;

use Doctrine\DBAL\Driver\Statement as DoctrineStatement;
use KoolKode\Database\DB;
use KoolKode\Database\ParamEncoderInterface;
use KoolKode\Database\PlaceholderList;
use KoolKode\Database\StatementInterface;
use KoolKode\Stream\StreamInterface;

/**
 * Adapts a wrapped Doctrine statement to the KoolKode Database API.
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
	 * The wrapped Doctrine statement.
	 * 
	 * @var DoctrineStatement
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
			$dconn = $this->conn->getDoctrineConnection();
			$sql = $this->sql;
			
			if($this->limit > 0)
			{
				$dconn->getDatabasePlatform()->modifyLimitQuery($sql, $this->limit, ($this->offset > 0) ? $this->offset : NULL);
			}
			
			$this->stmt = $dconn->prepare($sql);
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
			
			if($v instanceof StreamInterface)
			{
				$this->stmt->bindValue($k, fopen((string)$v, 'rb'), \PDO::PARAM_LOB);
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
		if($this->stmt !== NULL)
		{
			$this->stmt->closeCursor();
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
		if($this->stmt === NULL)
		{
			return false;
		}
		
		switch($style)
		{
			case DB::FETCH_BOTH:
				$style = \PDO::FETCH_BOTH;
				break;
			case DB::FETCH_NUM:
				$style = \PDO::FETCH_NUM;
				break;
			default:
				$style = \PDO::FETCH_ASSOC;
				break;
		}
		
		return $this->enhanced ? $this->transformRow($this->stmt->fetch($style)) : $this->stmt->fetch($style);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function fetchNextColumn($column)
	{
		if($this->stmt === NULL)
		{
			return false;
		}
		
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
		if($this->stmt === NULL)
		{
			return [];
		}
		
		switch($style)
		{
			case DB::FETCH_BOTH:
				$style = \PDO::FETCH_BOTH;
				break;
			case DB::FETCH_NUM:
				$style = \PDO::FETCH_NUM;
				break;
			default:
				$style = \PDO::FETCH_ASSOC;
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
		if($this->stmt === NULL)
		{
			return [];
		}
		
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
		if($this->stmt === NULL)
		{
			return [];
		}
		
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
