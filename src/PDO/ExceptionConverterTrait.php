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

use KoolKode\Database\Exception\DatabaseException;
use KoolKode\Database\Exception\ForeignKeyConstraintViolationException;
use KoolKode\Database\Exception\UniqueConstraintViolationException;
use KoolKode\Database\DB;

/**
 * Helper methods that allow for conversion of a PDO exception into an API exception.
 * 
 * @author Martin Schröder
 */
trait ExceptionConverterTrait
{
	/**
	 * Converts the given exception into a DB API exception.
	 * 
	 * @param \Exception $e
	 * @return DatabaseException
	 */
	protected function convertDatabaseException(\Exception $e)
	{
		if($e instanceof DatabaseException)
		{
			return $e;
		}
		
		// Check for PDO exception in order to determine FK violation:
		$cause = $e;
		
		if(!$cause instanceof \PDOException)
		{
			while(NULL !== ($cause = $cause->getPrevious()))
			{
				if($cause instanceof \PDOException)
				{
					break;
				}
			}
		}
		
		if($cause instanceof \PDOException)
		{
			switch($this->driverName)
			{
				case DB::DRIVER_MYSQL:
					return $this->convertMySqlException($cause, $e);
				case DB::DRIVER_POSTGRESQL:
					return $this->convertPostgresException($cause, $e);
				case DB::DRIVER_SQLITE:
					return $this->convertSqliteException($cause, $e);
			}
		}
		
		return new DatabaseException($e->getMessage(), 0, $e);
	}
	
	/**
	 * @param \PDOException $e
	 *
	 * @link http://www.sqlite.org/c3ref/c_abort.html
	 */
	protected function convertSqliteException(\PDOException $e, \Exception $root)
	{
		static $unique = [
				'must be unique',
				'is not unique',
				'are not unique',
				'UNIQUE constraint failed'
		];
	
		$message = $e->getMessage();
		$state = (string)isset($e->errorInfo[0]) ? $e->errorInfo[0] : $e->getCode();
	
		switch($state)
		{
			case '23000':
				foreach($unique as $u)
				{
					if(strpos($message, $u) !== false)
					{
						return new UniqueConstraintViolationException($root->getMessage(), 0, $root);
					}
				}
	
				if(stripos($message, 'foreign') !== false)
				{
					return new ForeignKeyConstraintViolationException($root->getMessage(), 0, $root);
				}
		}
	
		return new DatabaseException($root->getMessage(), 0, $root);
	}
	
	/**
	 * @param \PDOException $e
	 *
	 * @link http://dev.mysql.com/doc/refman/5.7/en/error-messages-client.html
	 * @link http://dev.mysql.com/doc/refman/5.7/en/error-messages-server.html
	 */
	protected function convertMySqlException(\PDOException $e, \Exception $root)
	{
		$code = isset($e->errorInfo[1]) ? $e->errorInfo[1] : $e->getCode();
	
		switch($code)
		{
			case '1216':
			case '1217':
			case '1451':
			case '1452':
			case '1701':
				return new ForeignKeyConstraintViolationException($root->getMessage(), 0, $root);
			case '1062':
			case '1557':
			case '1569':
			case '1586':
				return new UniqueConstraintViolationException($root->getMessage(), 0, $root);
		}
	
		return new DatabaseException($root->getMessage(), 0, $root);
	}
	
	/**
	 * @link http://www.postgresql.org/docs/9.3/static/errcodes-appendix.html
	 */
	protected function convertPostgresException(\PDOException $e, \Exception $root)
	{
		$state = (string)isset($e->errorInfo[0]) ? $e->errorInfo[0] : $e->getCode();
	
		switch($state)
		{
			case '0A000':
				if(strpos($e->getMessage(), 'truncate') !== false)
				{
					return new ForeignKeyConstraintViolationException($root->getMessage(), 0, $root);
				}
				break;
			case '23503':
				return new ForeignKeyConstraintViolationException($root->getMessage(), 0, $root);
			case '23505':
				return new UniqueConstraintViolationException($root->getMessage(), 0, $root);
		}
	
		return new DatabaseException($root->getMessage(), 0, $root);
	}
}
