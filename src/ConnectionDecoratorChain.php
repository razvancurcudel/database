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
 * Decorator chain that is invoked around method calls on a DB connection.
 * 
 * @author Martin Schröder
 */
class ConnectionDecoratorChain implements BaseConnectionInterface
{
	protected static $decorate = true;
	
	protected $conn;
	
	protected $decorators = [];
	
	protected $index = 0;
	
	public function __construct(ConnectionInterface $conn, array $decorators)
	{
		$this->conn = $conn;
		
		foreach($decorators as $decorator)
		{
			$dec = clone $decorator;
			$dec->setConnection($this);
			
			$this->decorators[] = $dec;
		}
	}
	
	public static function isDecorate()
	{
		$decorate = self::$decorate;
		self::$decorate = true;
		
		return $decorate;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute($sql, $prefix = NULL)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->execute($sql, $prefix);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->execute($sql, $prefix);
		}
		finally
		{
			self::$decorate = true;
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function prepare($sql, $prefix = NULL)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->prepare($sql, $prefix);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->prepare($sql, $prefix);
		}
		finally
		{
			self::$decorate = true;
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function insert($tableName, array $values, $prefix = NULL)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->insert($tableName, $values, $prefix);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->insert($tableName, $values, $prefix);
		}
		finally
		{
			self::$decorate = true;
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function upsert($tableName, array $key, array $values, $prefix = NULL)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->upsert($tableName, $key, $values, $prefix);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->upsert($tableName, $key, $values, $prefix);
		}
		finally
		{
			self::$decorate = true;
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function update($tableName, array $key, array $values, $prefix = NULL)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->update($tableName, $key, $values, $prefix);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->update($tableName, $key, $values, $prefix);
		}
		finally
		{
			self::$decorate = true;
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function delete($tableName, array $key, $prefix = NULL)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->delete($tableName, $key, $prefix);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->delete($tableName, $key, $prefix);
		}
		finally
		{
			self::$decorate = true;
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function lastInsertId($sequenceName, $prefix = NULL)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->lastInsertId($sequenceName, $prefix);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->lastInsertId($sequenceName, $prefix);
		}
		finally
		{
			self::$decorate = true;
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quote($value)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->quote($value);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->quote($value);
		}
		finally
		{
			self::$decorate = true;
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quoteIdentifier($identifier)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->quoteIdentifier($identifier);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->quoteIdentifier($identifier);
		}
		finally
		{
			self::$decorate = true;
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function applyPrefix($value, $prefix = NULL)
	{
		if(isset($this->decorators[$this->index]))
		{
			return $this->decorators[$this->index++]->applyPrefix($value, $prefix);
		}
		
		self::$decorate = false;
		
		try
		{
			return $this->conn->applyPrefix($value, $prefix);
		}
		finally
		{
			self::$decorate = true;
		}
	}
}
