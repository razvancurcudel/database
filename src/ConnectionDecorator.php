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
 * Base class for a database connection decorator.
 * 
 * @author Martin Schröder
 */
abstract class ConnectionDecorator implements ConnectionInterface
{
	/**
	 * The database connection being decorated.
	 * 
	 * @var ConnectionInterface
	 */
	protected $conn;
	
	/**
	 * Decorate the given database connection.
	 * 
	 * @param ConnectionInterface $conn
	 */
	public function __construct(ConnectionInterface $conn)
	{
		$this->conn = $conn;
	}
	
	/**
	 * Get the actual connection being decorated (works even in cas eof nested decorators).
	 * 
	 * @return ConnectionInterface
	 */
	public function getConnection()
	{
		if($this->conn instanceof ConnectionDecorator)
		{
			return $this->conn->getConnection();
		}
		
		return $this->conn;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getDriverName()
	{
		return $this->conn->getDriverName();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function hasOption($name)
	{
		return $this->conn->hasOption($name);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getOption($name)
	{
		if(func_num_args() > 1)
		{
			return $this->conn->getOption($name, func_get_arg(1));
		}
		
		return $this->conn->getOption($name);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function inTransaction()
	{
		return $this->conn->inTransaction();
	}

	/**
	 * {@inheritdoc}
	 */
	public function beginTransaction()
	{
		$this->conn->beginTransaction();
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function commit()
	{
		$this->conn->commit();
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function rollBack()
	{
		$this->conn->rollBack();
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute($sql, $prefix = NULL)
	{
		return $this->conn->execute($sql, $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function prepare($sql, $prefix = NULL)
	{
		return $this->conn->prepare($sql, $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function insert($tableName, array $values, $prefix = NULL)
	{
		return $this->conn->insert($tableName, $values, $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function upsert($tableName, array $key, array $values, $prefix = NULL)
	{
		return $this->conn->upsert($tableName, $key, $values, $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function update($tableName, array $key, array $values, $prefix = NULL)
	{
		return $this->conn->update($tableName, $key, $values, $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function delete($tableName, array $key, $prefix = NULL)
	{
		return $this->conn->delete($tableName, $key, $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function lastInsertId($sequenceName = NULL, $prefix = NULL)
	{
		return $this->conn->lastInsertId($sequenceName, $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quote($value)
	{
		return $this->conn->quote($value);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quoteIdentifier($identifier)
	{
		return $this->conn->quoteIdentifier($identifier);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function applyPrefix($value, $prefix = NULL)
	{
		return $this->conn->applyPrefix($value, $prefix);
	}
}
