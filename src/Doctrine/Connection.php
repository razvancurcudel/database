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

use Doctrine\DBAL\Connection as DoctrineConnection;
use KoolKode\Database\AbstractConnection;
use KoolKode\Database\ConnectionDecoratorChain;
use KoolKode\Database\DB;

/**
 * Adapts a wrapped Doctrine DBAL connection to the KoolKode Database API.
 * 
 * @author Martin Schröder
 */
class Connection extends AbstractConnection
{
	/**
	 * The wrapped Doctrine connection.
	 * 
	 * @var DoctrineConnection
	 */
	protected $conn;
	
	/**
	 * Create a new connection wrapping a Doctrine DBAL connection.
	 * 
	 * @param DoctrineConnection $conn 
	 * @param array<string, mixed> $options
	 */
	public function __construct(DoctrineConnection $conn, array $options = [])
	{
		$this->conn = $conn;
		$this->options = $options;
		
		switch($conn->getDriver()->getName())
		{
			case 'pdo_sqlite':
				$this->driverName = DB::DRIVER_SQLITE;
				break;
			case 'pdo_mysql':
			case 'drizzle_pdo_mysql':
			case 'mysqli':
				$this->driverName = DB::DRIVER_MYSQL;
				break;
			case 'pdo_pgsql':
				$this->driverName = DB::DRIVER_POSTGRESQL;
				break;
			case 'pdo_oci':
			case 'oci8':
				$this->driverName = DB::DRIVER_ORACLE;
				break;
			case 'pdo_sqlsrv':
			case 'sqlsrv':
				$this->driverName = DB::DRIVER_MSSQL;
				break;
			case 'ibm_db2':
				$this->driverName = DB::DRIVER_DB2;
				break;
		}
	}
	
	/**
	 * @return DoctrineConnection
	 */
	public function getDoctrineConnection()
	{
		return $this->conn;
	}
	
	protected function performBeginTransaction($identifier = NULL)
	{
		if($identifier === NULL)
		{
			return $this->conn->beginTransaction();
		}
		
		$this->conn->createSavepoint($identifier);
	}
	
	protected function performCommit($identifier = NULL)
	{
		if($identifier === NULL)
		{
			return $this->conn->commit();
		}
		
		$this->conn->createSavepoint($identifier);
	}
	
	protected function performRollBack($identifier = NULL)
	{
		if($identifier === NULL)
		{
			return $this->conn->rollBack();
		}
		
		$this->conn->rollbackSavepoint($identifier);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function prepare($sql, $prefix = NULL)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->prepare($sql, $prefix);
		}
		
		return new Statement($this, $this->prepareSql($sql, $prefix));
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function lastInsertId($sequenceName, $prefix = NULL)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->lastInsertId($sequenceName, $prefix);
		}
		
		return $this->determineLastInsertId([$this->conn, 'lastInsertId'], $sequenceName, $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quote($value)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->quote($value);
		}
		
		return $this->conn->quote($value);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quoteIdentifier($identifier)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->quoteIdentifier($identifier);
		}
		
		return $this->conn->quoteIdentifier($identifier);
	}
}
