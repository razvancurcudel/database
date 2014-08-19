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

/**
 * Adapts a wrapped PDO connection to the KoolKode Database API.
 * 
 * @author Martin Schröder
 */
class Connection implements ConnectionInterface
{
	/**
	 * The wrapped PDO instance.
	 * 
	 * @var \PDO
	 */
	protected $pdo;
	
	protected $transLevel = 0;
	
	protected $driverName;
	
	protected $options = [];
	
	/**
	 * Create a new connection wrapping a PDO instance.
	 * 
	 * @param \PDO $pdo
	 * @param array<string, mixed> $options
	 */
	public function __construct(\PDO $pdo, array $options = [])
	{
		$this->pdo = $pdo;
		$this->options = $options;
		
		$this->driverName = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
		
		switch($this->driverName)
		{
			case DB::DRIVER_SQLITE:
				$this->initializeSqlite();
				break;
			case DB::DRIVER_MYSQL:
				$this->initializeMySQL();
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
	 * @return \PDO
	 */
	public function getPDO()
	{
		return $this->pdo;
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
	 * Get the server software version.
	 * 
	 * @return string
	 */
	public function getServerVersion()
	{
		try
		{
			$version = $this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
		}
		catch(\PDOException $e)
		{
			return NULL;
		}
		
		return $version;
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
	public function lastInsertId($sequenceName = NULL, $prefix = NULL)
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
		
		$seq = str_replace(DB::SCHEMA_OBJECT_PREFIX, ($prefix === NULL) ? '' : $prefix, $sequenceName);
		$seq = preg_replace_callback("'`([^`]*)`'", function($m) {
			return $this->quoteIdentifier($m[1]);
		}, $seq);
		
		return $this->pdo->lastInsertId($seq);
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
	
	protected function initializeSqlite()
	{
		$this->execute("PRAGMA foreign_keys = ON");
		
		if(array_key_exists(DB::OPTION_SQLITE_PRAGMA, $this->options))
		{
			foreach((array)$this->options[DB::OPTION_SQLITE_PRAGMA] as $k => $v)
			{
				$this->execute(sprintf("PRAGMA %s = %s", $k, $v));
			}
		}
	}
	
	protected function initializeMySQL()
	{
		$encoding = array_key_exists(DB::OPTION_ENCODING, $this->options) ? $this->options[DB::OPTION_ENCODING] : 'UTF-8';
		$encoding = strtolower(str_replace(['_', '-'], '', $encoding));
		
		$sql = ['NAMES :names'];
		$params = ['names' => $encoding];
		
		if(array_key_exists(DB::OPTION_TIMEZONE, $this->options))
		{
			$tz = (string)$this->options[DB::OPTION_TIMEZONE];
			
			$timezone = new \DateTimeZone(($tz == 'default') ? date_default_timezone_get() : $tz);
			$offset = $timezone->getOffset(new \DateTime('now', $timezone));
		}
		else
		{
			$offset = 0;
		}
		
		$d1 = new \DateTime();
		$d2 = new \DateTime();
		$sign = ($offset < 0) ? '-' : '+';
		
		$d2->add(new \DateInterval('PT' . abs($offset) . 'S'));
		$diff = $d2->diff($d1);
		
		$sql[] = 'SESSION time_zone = :timezone';
		$params['timezone'] = sprintf('%s%02u:%02u', $sign, $diff->h, $diff->i);
		
		if(!empty($sql))
		{
			$stmt = $this->prepare("SET " . implode(', ', $sql));
			$stmt->bindAll($params);
			$stmt->execute();
		}
	}
}
