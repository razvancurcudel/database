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

use Psr\Log\LoggerInterface;

/**
 * Extends a PDO connection providing some convenient extra features.
 * 
 * @author Martin Schröder
 */
class Connection extends \PDO
{
	const TABLE_PREFIX = '#__';
	
	protected $debug = false;
	
	protected $tablePrefix = '';
	
	protected $driverName;
	
	protected $transLevel = 0;
	
	protected $logger;
	
	protected $encoders = [];
	
	public function __construct($dsn, $username = NULL, $password = NULL, array $options = [])
	{
		$options[self::ATTR_STATEMENT_CLASS] = [static::getStatementClass(), [$this]];
		
		parent::__construct($dsn, $username, $password, $options);
		
		$this->setAttribute(self::ATTR_ERRMODE, self::ERRMODE_EXCEPTION);
		
		$this->driverName = $this->getAttribute(self::ATTR_DRIVER_NAME);
	}
	
	public static function getStatementClass()
	{
		return PreparedStatement::class;
	}
	
	public function getLogger()
	{
		return $this->logger;
	}
	
	public function setLogger(LoggerInterface $logger = NULL)
	{
		$this->logger = $logger;
	}
	
	public function registerParamEncoder(ParamEncoderInterface $encoder)
	{
		if(!in_array($encoder, $this->encoders, true))
		{
			$this->encoders[] = $encoder;
		}
	}
	
	public function unregisterParamEncoder(ParamEncoderInterface $encoder)
	{
		if(false !== ($index = array_search($encoder, $this->encoders, true)))
		{
			unset($this->encoders[$index]);
		}
	}
	
	public function isDebug()
	{
		return $this->debug;
	}
	
	public function setDebug($debug)
	{
		$this->debug = $debug ? true : false;
	}
	
	public function isMySQL()
	{
		return $this->driverName == 'mysql';
	}
	
	public function isSqlite()
	{
		return $this->driverName == 'sqlite';
	}
	
	public function isPostgreSQL()
	{
		return $this->driverName == 'pgsql';
	}
	
	public function setTablePrefix($tablePrefix)
	{
		$this->tablePrefix = trim($tablePrefix);
	}
	
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
	}
	
	protected function performBeginTransaction($identifier = NULL)
	{
		if($identifier === NULL)
		{
			return parent::beginTransaction();
		}
	
		switch($this->driverName)
		{
			case 'mysql':
			case 'sqlite':
			case 'pgsql':
			case 'oci':
			case 'db2':
				$this->exec("SAVEPOINT " . $identifier);
				break;
			case 'mssql':
				$this->exec("SAVE TRANSACTION " . $identifier);
				break;
			default:
				throw new \RuntimeException(sprintf('Database driver "%s" does not support nested transactions', $this->driverName));
		}
	}
	
	public function commit()
	{
		$this->transLevel--;
	
		if($this->transLevel == 0)
		{
			return $this->performCommit();
		}
		
		return $this->performCommit('LEVEL' . $this->transLevel);
	}
	
	protected function performCommit($identifier = NULL)
	{
		if($identifier === NULL)
		{
			return parent::commit();
		}
		
		switch($this->driverName)
		{
			case 'mysql':
			case 'sqlite':
			case 'pgsql':
			case 'oci':
			case 'db2':
				$this->exec("SAVEPOINT " . $identifier);
				break;
			case 'mssql':
				$this->exec("SAVE TRANSACTION " . $identifier);
				break;
			default:
				throw new \RuntimeException(sprintf('Database driver "%s" does not support nested transactions', $this->driverName));
		}
	}
	
	public function rollBack()
	{
		$this->transLevel--;
	
		if($this->transLevel == 0)
		{
			return $this->performRollBack();
		}
		
		return $this->performRollBack('LEVEL' . $this->transLevel);
	}
	
	protected function performRollBack($identifier = NULL)
	{
		if($identifier === NULL)
		{
			return parent::rollBack();
		}
		
		switch($this->driverName)
		{
			case 'mysql':
			case 'sqlite':
			case 'pgsql':
			case 'db2':
				$this->exec("ROLLBACK TO SAVEPOINT " . $identifier);
				break;
			case 'oci':
				$this->exec("ROLLBACK TO " . $identifier);
				break;
			case 'mssql':
				$this->exec("ROLLBACK TRANSACTION " . $identifier);
				break;
			default:
				throw new \RuntimeException(sprintf('Database driver "%s" does not support nested transactions', $this->driverName));
		}
	}
	
	public function exec($sql)
	{
		$stmt = $this->prepare($sql);
		$stmt->execute();
		
		return $stmt->rowCount();
	}
	
	public function query($sql, $fetchType = NULL, $arg1 = NULL, $arg2 = NULL)
	{
		$stmt = $this->prepare($sql);
		$stmt->execute();
		
		switch($fetchType)
		{
			case NULL:
				break;
			case self::FETCH_CLASS:
				$stmt->setFetchMode(self::FETCH_CLASS, (string)$arg1, (array)$arg2);
				break;
			default:
				$stmt->setFetchMode($fetchType, $arg1);
		}
		
		return $stmt;
	}
	
	/**
	 * @return PDOStatement
	 */
	public function prepare($sql, $options = NULL)
	{
		$sql = trim(preg_replace("'\s+'", ' ', $sql));
		$sql = str_replace(self::TABLE_PREFIX, $this->tablePrefix, $sql);
		$sql = preg_replace_callback("'`([^`]*)`'", function($m) {
			return $this->quoteIdentifier($m[1]);
		}, $sql);
		
		if($options === NULL)
		{
			$stmt = parent::prepare($sql);
		}
		else
		{
			$stmt = parent::prepare($sql, $options);
		}
		
		$stmt->setFetchMode(self::FETCH_ASSOC);
		
		return $stmt;
	}
	
	public function encodeParam($value, $type = NULL)
	{
		$done = false;
	
		foreach($this->encoders as $encoder)
		{
			$result = $encoder->encodeParam($this, $value, $done);
				
			if($done)
			{
				return $result;
			}
		}
		
		return $value;
	}
	
	public function quote($v, $type = NULL)
	{
		$done = false;
		
		foreach($this->encoders as $encoder)
		{
			$result = $encoder->encodeParam($this, $v, $done);
			
			if($done)
			{
				if($type === NULL)
				{
					return parent::quote($result);
				}
				
				return parent::query($result, $type);
			}
		}
		
		if(is_bool($v))
		{
			$v = $v ? 1 : 0;
		}
		
		if($type === NULL)
		{
			return parent::quote($v);
		}
		
		return parent::query($v, $type);
	}
	
	public function quoteIdentifier($identifier)
	{
		switch($this->driverName)
		{
			case 'mysql':
				return '`' . str_replace('`', '``', $identifier) . '`';
			case 'mssql':
				return '[' . str_replace(['[', ']'], '', $identifier) . ']';
		}
		
		return '"' . str_replace('"', '\\"', $identifier) . '"';
	}
}
