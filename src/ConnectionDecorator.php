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
abstract class ConnectionDecorator implements BaseConnectionInterface
{
	/**
	 * The database connection being decorated.
	 * 
	 * @var ConnectionInterface
	 */
	protected $conn;
	
	public final function setConnection(BaseConnectionInterface $conn)
	{
		$this->conn = $conn;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute($sql)
	{
		return $this->conn->execute($sql);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function prepare($sql)
	{
		return $this->conn->prepare($sql);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function insert($tableName, array $values)
	{
		return $this->conn->insert($tableName, $values);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function upsert($tableName, array $key, array $values)
	{
		return $this->conn->upsert($tableName, $key, $values);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function update($tableName, array $key, array $values)
	{
		return $this->conn->update($tableName, $key, $values);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function delete($tableName, array $key)
	{
		return $this->conn->delete($tableName, $key);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function lastInsertId($sequenceName)
	{
		return $this->conn->lastInsertId($sequenceName);
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
	public function applyPrefix($value)
	{
		return $this->conn->applyPrefix($value);
	}
}
