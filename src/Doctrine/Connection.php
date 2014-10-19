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
use KoolKode\Database\ConnectionInterface;
use KoolKode\Database\DB;

/**
 * Adapts a wrapped Doctrine DBAL connection to the KoolKode Database API.
 * 
 * @author Martin Schröder
 */
class Connection implements ConnectionInterface
{
	/**
	 * The wrapped Doctrine connection.
	 * 
	 * @var DoctrineConnection
	 */
	protected $conn;
	
	protected $driverName;
	
	protected $transLevel = 0;
	
	protected $options = [];
	
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
	 * {@inheritdoc}
	 */
	public function hasOption($name)
	{
		return array_key_exists($name, $this->options);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getOption($name)
	{
		if(array_key_exists($name, $this->options))
		{
			return $this->options[$name];
		}
		
		if(func_num_args() > 1)
		{
			return func_get_arg(1);
		}
		
		throw new \OutOfBoundsException(sprintf('Option "%s" is not available', $name));
	}
	
	/**
	 * @return DoctrineConnection
	 */
	public function getDoctrineConnection()
	{
		return $this->conn;
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
		return $this->conn->isTransactionActive();
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
			return $this->conn->beginTransaction();
		}
		
		$this->conn->createSavepoint($identifier);
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
			return $this->conn->commit();
		}
		
		$this->conn->createSavepoint($identifier);
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
			return $this->conn->rollBack();
		}
		
		$this->conn->rollbackSavepoint($identifier);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute($sql, $prefix = NULL)
	{
		return $this->prepare($sql, $prefix)->execute();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function prepare($sql, $prefix = NULL)
	{
		$sql = trim(preg_replace("'\s+'", ' ', $sql));
		$sql = str_replace(DB::SCHEMA_OBJECT_PREFIX, ($prefix === NULL) ? '' : $prefix, $sql);
		$sql = preg_replace_callback("'`([^`]*)`'", function($m) {
			return $this->quoteIdentifier($m[1]);
		}, $sql);
		
		return new Statement($this, $sql);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function insert($tableName, array $values, $prefix = NULL)
	{
		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)',
			$this->quoteIdentifier($tableName),
			implode(', ', $this->buildNameList($values)),
			implode(', ', $this->buildParamList($values))
		);
		
		return $this->prepare($sql, $prefix)->bindAll($values)->execute();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function upsert($tableName, array $key, array $values, $prefix = NULL)
	{
		$params = array_merge($this->prefixKeys('v', $values), $this->prefixKeys('k', $key));
		
		// Ensure at least one value is set in update statement.
		if(empty($values))
		{
			foreach($params as $k => $v)
			{
				$values[substr($k, 1)] = $v;
		
				break;
			}
		}
		
		$this->beginTransaction();
		
		try
		{
			$sql = sprintf(
				'SELECT COUNT(*) FROM %s WHERE %s',
				$this->quoteIdentifier($tableName),
				implode(' AND ', $this->buildIdentity($key))
			);
			
			$stmt = $this->prepare($sql, $prefix)->bindAll($key);
			$stmt->execute();
			
			if($stmt->fetchNextColumn(0))
			{
				$this->update($tableName, $key, $values, $prefix);
			}
			else
			{
				$this->insert($tableName, array_merge($values, $key), $prefix);
			}
		}
		catch(\Exception $e)
		{
			$this->rollBack();
			
			throw $e;
		}
		
		$this->commit();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function update($tableName, array $key, array $values, $prefix = NULL)
	{
		$params = array_merge($this->prefixKeys('v', $values), $this->prefixKeys('k', $key));
		
		$sql = sprintf(
			'UPDATE %s SET %s WHERE %s',
			$this->quoteIdentifier($tableName),
			implode(', ', $this->buildIdentity($values, 'v')),
			implode(' AND ', $this->buildIdentity($key, 'k'))
		);
		
		return $this->prepare($sql, $prefix)->bindAll($params)->execute();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function delete($tableName, array $key, $prefix = NULL)
	{
		$sql = sprintf(
			'DELETE FROM %s WHERE %s',
			$this->quoteIdentifier($tableName),
			implode(' AND ', $this->buildIdentity($key))	
		);
		
		return $this->prepare($sql, $prefix)->bindAll($key)->execute();
	}
	
	/**
	 * Apply a string prefix to all ay and return the resulting array.
	 * 
	 * @param string $prefix
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	protected function prefixKeys($prefix, array $data)
	{
		$result = [];
		
		foreach($data as $k => $v)
		{
			$result[$prefix . $k] = $v;
		}
		
		return $result;
	}
	
	/**
	 * Build a list of quoted column names from the keys of the given array.
	 * 
	 * @param array<string, mixed> $values
	 * @return array<string>
	 */
	protected function buildNameList(array $values)
	{
		return array_map(function($key) {
			return $this->quoteIdentifier($key);
		}, array_keys($values));
	}
	
	/**
	 * Build a list of parameter placeholders named using keys from the given array.
	 * 
	 * @param array<string, mixed> $values
	 * @param string $namePrefix
	 * @return array<string>
	 */
	protected function buildParamList(array $values, $namePrefix = '')
	{
		return array_map(function($key) use($namePrefix) {
			return ':' . $namePrefix . $key;
		}, array_keys($values));
	}
	
	/**
	 * Build an identity (column = :value) list from the given key => value pairs.
	 * 
	 * @param array<string, mixed> $values
	 * @param string $namePrefix
	 * @return array<string>
	 */
	protected function buildIdentity(array $values, $namePrefix = '')
	{
		return array_map(function($key) use($namePrefix) {
			return $this->quoteIdentifier($key) . ' = :' . $namePrefix . $key;
		}, array_keys($values));
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function lastInsertId($sequenceName = NULL, $prefix = NULL)
	{
		if($sequenceName === NULL)
		{
			return $this->conn->lastInsertId();
		}
		
		$seq = str_replace(DB::SCHEMA_OBJECT_PREFIX, ($prefix === NULL) ? '' : $prefix, $sequenceName);
		$seq = preg_replace_callback("'`([^`]*)`'", function($m) {
			return $this->quoteIdentifier($m[1]);
		}, $seq);
		
		return $this->conn->lastInsertId($seq);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quoteIdentifier($identifier)
	{
		return $this->conn->quoteIdentifier($identifier);
	}
}
