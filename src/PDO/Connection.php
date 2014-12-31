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

use KoolKode\Database\AbstractConnection;
use KoolKode\Database\DB;

/**
 * Adapts a wrapped PDO connection to the KoolKode Database API.
 * 
 * @author Martin Schröder
 */
class Connection extends AbstractConnection
{
	/**
	 * The wrapped PDO instance.
	 * 
	 * @var \PDO
	 */
	protected $pdo;
	
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
	 * @return \PDO
	 */
	public function getPDO()
	{
		return $this->pdo;
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
	public function prepare($sql, $prefix = NULL)
	{
		return new Statement($this, $this->prepareSql($sql, $prefix));
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function lastInsertId($sequenceName, $prefix = NULL)
	{
		switch($this->driverName)
		{
			case DB::DRIVER_MYSQL:
			case DB::DRIVER_SQLITE:
				return $this->pdo->lastInsertId();
			case DB::DRIVER_POSTGRESQL:
				if(is_array($sequenceName))
				{
					$sequenceName = vsprintf('%s_%s_seq', $sequenceName);
				}
				return $this->pdo->lastInsertId($this->prepareSql($sequenceName, $prefix));
			case DB::DRIVER_MYSQL:
				$stmt = $this->pdo->prepare("SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS int)");
				$stmt->execute();
					
				return (int)$stmt->fetchColumn();
		}
		
		return $this->pdo->lastInsertId($this->prepareSql($sequenceName, $prefix));
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function quote($value)
	{
		return $this->pdo->quote($value);
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
