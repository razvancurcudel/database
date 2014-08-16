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

use KoolKode\Database\ConnectionInterface;
use KoolKode\Database\DB;
use Psr\Log\LoggerInterface;

/**
 * Adapts a wrapped PDO connection to the KoolKode Database API.
 * 
 * @author Martin Schröder
 */
class Connection implements ConnectionInterface
{
	protected $pdo;
	
	protected $transLevel = 0;
	
	protected $logger;
	
	protected $driverName;
	
	protected $tablePrefix;
	
	public function __construct(\PDO $pdo, $tablePrefix = '')
	{
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		
		$this->pdo = $pdo;
		$this->tablePrefix = (string)$tablePrefix;
		
		$this->driverName = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
	}
	
	public function setLogger(LoggerInterface $logger)
	{
		$this->logger = $logger;
		
		return $this;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getDriverName()
	{
		return $this->driverName;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function inTransaction()
	{
		return $this->pdo->inTransaction();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function beginTransaction()
	{
		if($this->transLevel == 0)
		{
			$this->performBeginTransaction();
		}
		else
		{
			$this->performBeginTransaction('LEVEL' . $this->transLevel);
		}
		
		$this->transLevel++;
		
		return $this;
	}
	
	protected function performBeginTransaction($identifier = NULL)
	{
		if($identifier === NULL)
		{
			return $this->pdo->beginTransaction();
		}
	
		switch($this->driverName)
		{
			case DB::DRIVER_MYSQL:
			case DB::DRIVER_SQLITE:
			case DB::DRIVER_POSTGRESQL:
			case DB::DRIVER_ORACLE:
			case DB::DRIVER_DB2:
				$this->pdo->exec("SAVEPOINT " . $identifier);
				break;
			case DB::DRIVER_MSSQL:
				$this->pdo->exec("SAVE TRANSACTION " . $identifier);
				break;
			default:
				throw new \RuntimeException(sprintf('Database driver "%s" does not support nested transactions', $this->driverName));
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function commit()
	{
		$this->transLevel--;
	
		if($this->transLevel == 0)
		{
			$this->performCommit();
		}
		else
		{
			$this->performCommit('LEVEL' . $this->transLevel);
		}
		
		return $this;
	}
	
	protected function performCommit($identifier = NULL)
	{
		if($identifier === NULL)
		{
			return $this->pdo->commit();
		}
		
		switch($this->driverName)
		{
			case DB::DRIVER_MYSQL:
			case DB::DRIVER_SQLITE:
			case DB::DRIVER_POSTGRESQL:
			case DB::DRIVER_ORACLE:
			case DB::DRIVER_DB2:
				$this->pdo->exec("SAVEPOINT " . $identifier);
				break;
			case DB::DRIVER_MSSQL:
				$this->pdo->exec("SAVE TRANSACTION " . $identifier);
				break;
			default:
				throw new \RuntimeException(sprintf('Database driver "%s" does not support nested transactions', $this->driverName));
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function rollBack()
	{
		$this->transLevel--;
	
		if($this->transLevel == 0)
		{
			$this->performRollBack();
		}
		else
		{
			$this->performRollBack('LEVEL' . $this->transLevel);
		}
		
		return $this;
	}
	
	protected function performRollBack($identifier = NULL)
	{
		if($identifier === NULL)
		{
			return $this->pdo->rollBack();
		}
		
		switch($this->driverName)
		{
			case DB::DRIVER_MYSQL:
			case DB::DRIVER_SQLITE:
			case DB::DRIVER_POSTGRESQL:
			case DB::DRIVER_DB2:
				$this->pdo->exec("ROLLBACK TO SAVEPOINT " . $identifier);
				break;
			case DB::DRIVER_ORACLE:
				$this->pdo->exec("ROLLBACK TO " . $identifier);
				break;
			case DB::DRIVER_MSSQL:
				$this->pdo->exec("ROLLBACK TRANSACTION " . $identifier);
				break;
			default:
				throw new \RuntimeException(sprintf('Database driver "%s" does not support nested transactions', $this->driverName));
		}
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute($sql)
	{
		$stmt = $this->prepare($sql);
		
		return $stmt->execute();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function prepare($sql)
	{
		$sql = trim(preg_replace("'\s+'", ' ', $sql));
		$sql = str_replace(DB::OBJECT_NAME_PREFIX, $this->tablePrefix, $sql);
		$sql = preg_replace_callback("'`([^`]*)`'", function($m) {
			return $this->quoteIdentifier($m[1]);
		}, $sql);
		
		$stmt = $this->pdo->prepare($sql);
		$stmt->setFetchMode(\PDO::FETCH_ASSOC);
		
		return new Statement($this, $stmt);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function lastInsertId($sequenceName = NULL)
	{
		if($this->driverName == DB::DRIVER_MSSQL)
		{
			$stmt = $this->pdo->prepare("SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS int)");
			$stmt->execute();
			
			return (int)$stmt->fetchColumn();
		}
		
		if($sequenceName === NULL)
		{
			return $this->pdo->lastInsertId();
		}
		
		return $this->pdo->lastInsertId($sequenceName);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quoteIdentifier($identifier)
	{
		switch($this->driverName)
		{
			case DB::DRIVER_MYSQL:
				return '`' . str_replace('`', '``', $identifier) . '`';
			case DB::DRIVER_MSSQL:
				return '[' . str_replace(['[', ']'], '', $identifier) . ']';
		}
		
		return '"' . str_replace('"', '\\"', $identifier) . '"';
	}
}
