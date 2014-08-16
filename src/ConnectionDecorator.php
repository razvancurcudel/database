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
	public function prepare($sql)
	{
		return $this->conn->prepare($sql);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function lastInsertId($sequenceName = NULL)
	{
		return $this->conn->lastInsertId($sequenceName);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quoteIdentifier($identifier)
	{
		return $this->conn->quoteIdentifier($identifier);
	}
}
