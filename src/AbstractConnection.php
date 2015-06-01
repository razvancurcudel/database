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

use KoolKode\Event\EventDispatcherInterface;

/**
 * Base class for KoolKode Database connections.
 * 
 * @author Martin Schröder
 */
abstract class AbstractConnection implements ConnectionInterface
{
	/**
	 * Current transaction level / depth.
	 * 
	 * @var integer
	 */
	protected $transLevel = 0;
	
	/**
	 * Normalized DB driver name.
	 * 
	 * @var string
	 */
	protected $driverName;
	
	/**
	 * DB adapter options.
	 * 
	 * @var array
	 */
	protected $options = [];
	
	/**
	 * Registered connection decorators.
	 * 
	 * @var array<ConnectionDecorator>
	 */
	protected $decorators = [];
	
	/**
	 * Optional event dispatcher.
	 * 
	 * @var EventDispatcherInterface
	 */
	protected $eventDispatcher;
	
	/**
	 * {@inheritdoc}
	 */
	public function addDecorator(ConnectionDecorator $decorator)
	{
		$this->decorators[] = $decorator;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function removeDecorator(ConnectionDecorator $decorator)
	{
		if(false !== ($index = array_search($decorator, $this->decorators, true)))
		{
			array_splice($this->decorators, $index, 1);
		}
	}
	
	/**
	 * Inject the event dispatcher instance.
	 * 
	 * @param EventDispatcherInterface $dispatcher
	 */
	public function setEventDispatcher(EventDispatcherInterface $dispatcher = NULL)
	{
		$this->eventDispatcher = $dispatcher;
	}
	
	/**
	 * Dispatch the given notification event.
	 * 
	 * @param object $event
	 */
	public function notify($event)
	{
		if($this->eventDispatcher !== NULL)
		{
			$this->eventDispatcher->notify($event);
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
	 * {@inheritdoc}
	 */
	public function getDriverName()
	{
		return $this->driverName;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function getPlatform()
	{
		switch($this->driverName)
		{
			case DB::DRIVER_MYSQL:
				return new Platform\MySqlPlatform($this);
			case DB::DRIVER_POSTGRESQL:
				return new Platform\PostgreSqlPlatform($this);
			case DB::DRIVER_SQLITE:
				return new Platform\SqlitePlatform($this);
		}
		
		throw new \RuntimeException(sprintf('No platform found for DB driver "%s"', $this->driverName));
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function inTransaction()
	{
		return $this->transLevel > 0;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function beginTransaction()
	{
		try
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
		catch(\Exception $e)
		{
			throw $this->convertException($e);
		}
	}
	
	protected abstract function performBeginTransaction($identifier = NULL);
	
	/**
	 * {@inheritdoc}
	 */
	public function commit()
	{
		try
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
		catch(\Exception $e)
		{
			throw $this->convertException($e);
		}
	}
	
	protected abstract function performCommit($identifier = NULL);
	
	/**
	 * {@inheritdoc}
	 */
	public function rollBack()
	{
		try
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
		catch(\Exception $e)
		{
			throw $this->convertException($e);
		}
	}
	
	protected abstract function performRollBack($identifier = NULL);
	
	/**
	 * {@inheritdoc}
	 */
	public function execute($sql)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->execute($sql);
		}
		
		return $this->prepare($sql)->execute();
	}
	
	protected function prepareSql($sql)
	{
		$sql = trim(preg_replace("'\s+'", ' ', $sql));
		$sql = $this->applyPrefix($sql);
		
		return preg_replace_callback("'`([^`]*)`'", function($m) {
			return $this->quoteIdentifier($m[1]);
		}, $sql);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function insert($tableName, array $values)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->insert($tableName, $values);
		}
		
		$sql = sprintf(
			'INSERT INTO %s (%s) VALUES (%s)',
			$this->quoteIdentifier($tableName),
			implode(', ', $this->buildNameList($values)),
			implode(', ', $this->buildParamList($values))
		);
		
		return $this->prepare($sql)->bindAll($values)->execute();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function upsert($tableName, array $key, array $values)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->upsert($tableName, $key, $values);
		}
		
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
		
// 		switch($this->driverName)
// 		{
// 			case DB::DRIVER_SQLITE:
				
// 				$sql = sprintf(
// 					'INSERT OR REPLACE INTO %s (%s) VALUES (%s)',
// 					$this->quoteIdentifier($tableName),
// 					implode(', ', $this->buildNameList(array_merge($values, $key))),
// 					implode(', ', $this->buildParamList($params))
// 				);
				
// 				return $this->prepare($sql)->bindAll($params)->execute();
				
// 			case DB::DRIVER_MYSQL:
				
// 				$sql = sprintf(
// 					'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE ',
// 					$this->quoteIdentifier($tableName),
// 					implode(', ', $this->buildNameList(array_merge($values, $key))),
// 					implode(', ', $this->buildParamList($params))
// 				);
				
// 				foreach(array_keys($values) as $i => $key)
// 				{
// 					$sql .= sprintf(
// 						'%s%s = VALUES(%s)',
// 						($i != 0) ? ', ' : '',
// 						$this->quoteIdentifier($key),
// 						$this->quoteIdentifier($key)
// 					);
// 				}
				
// 				return $this->prepare($sql)->bindAll($params)->execute();
// 		}
		
		$this->beginTransaction();
		
		try
		{
			$sql = sprintf(
				'SELECT 1 FROM %s WHERE %s',
				$this->quoteIdentifier($tableName),
				implode(' AND ', $this->buildIdentity($key, '', true))
			);
			
			$stmt = $this->prepare($sql)->bindAll($this->prepareParams($key));
			$stmt->setLimit(1);
			$stmt->execute();
			
			if($stmt->fetchNextColumn(0))
			{
				$this->update($tableName, $key, $values);
			}
			else
			{
				$this->insert($tableName, array_merge($values, $key));
			}
		}
		catch(\Exception $e)
		{
			$this->rollBack();
			
			throw $this->convertException($e);
		}
		
		$this->commit();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function update($tableName, array $key, array $values)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->update($tableName, $key, $values);
		}
		
		$params = array_merge($this->prefixKeys('v', $values), $this->prefixKeys('k', $key));
		
		$sql = sprintf(
			'UPDATE %s SET %s WHERE %s',
			$this->quoteIdentifier($tableName),
			implode(', ', $this->buildIdentity($values, 'v')),
			implode(' AND ', $this->buildIdentity($key, 'k', true))
		);
		
		return $this->prepare($sql)->bindAll($this->prepareParams($params))->execute();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function delete($tableName, array $key)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->delete($tableName, $key);
		}
		
		$sql = sprintf(
			'DELETE FROM %s WHERE %s',
			$this->quoteIdentifier($tableName),
			implode(' AND ', $this->buildIdentity($key, '', true))	
		);
		
		return $this->prepare($sql)->bindAll($this->prepareParams($key))->execute();
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
	 * @param boolean $allowIn
	 * @return array<string>
	 */
	protected function buildIdentity(array $values, $namePrefix = '', $allowIn = false)
	{
		if(!$allowIn)
		{
			return array_map(function($key) use($namePrefix) {
				return $this->quoteIdentifier($key) . ' = :' . $namePrefix . $key;
			}, array_keys($values));
		}
		
		$identity = [];
		
		foreach($values as $k => $v)
		{
			if(is_array($v))
			{
				$placeholders = [];
				
				foreach(range(0, count($v) - 1) as $i)
				{
					$placeholders[] = sprintf(':%s%s%u', $namePrefix, $k, $i);
				}
				
				$identity[] = sprintf('%s IN (%s)', $this->quoteIdentifier($k), implode(', ', $placeholders));
			}
			else
			{
				$identity[] = sprintf('%s = :%s%s', $this->quoteIdentifier($k), $namePrefix, $k);
			}
		}
		
		return $identity;
	}
	
	/**
	 * Prepare query params supporting arrays as IN() clause.
	 * 
	 * @param array $params
	 * @return array
	 */
	protected function prepareParams(array $params)
	{
		$prepared = [];
		
		foreach($params as $k => $v)
		{
			if(is_array($v))
			{
				foreach(array_values($v) as $i => $val)
				{
					$prepared[sprintf('%s%u', $k, $i)] = $val;
				}
			}
			else
			{
				$prepared[(string)$k] = $v;
			}
		}
		
		return $prepared;
	}
	
	/**
	 * Determine the last inserted ID value.
	 * 
	 * @param callable $callback The native callback to be invoked.
	 * @param mixed $sequenceName Name of a sequence or an array containing a table name and a column name of a SERIAL column.
	 * @return integer
	 */
	protected function determineLastInsertId(callable $callback, $sequenceName)
	{
		try
		{
			switch($this->driverName)
			{
				case DB::DRIVER_MYSQL:
				case DB::DRIVER_SQLITE:
					return (int)$callback();
				case DB::DRIVER_POSTGRESQL:
					if(is_array($sequenceName))
					{
						$stmt = $this->prepare("SELECT currval(pg_get_serial_sequence(:table, :col))");
						$stmt->bindValue('table', $this->prepareSql($sequenceName[0]));
						$stmt->bindValue('col', $sequenceName[1]);
						$stmt->execute();
							
						return (int)$stmt->fetchNextColumn(0);
					}
					return $callback($this->prepareSql($sequenceName));
				case DB::DRIVER_MYSQL:
					$stmt = $this->prepare("SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS int)");
					$stmt->execute();
						
					return (int)$stmt->fetchNextColumn(0);
			}
		
			return (int)$callback($this->prepareSql($sequenceName));
		}
		catch(\Exception $e)
		{
			throw $this->convertException($e);
		}
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
		
		switch($this->driverName)
		{
			case DB::DRIVER_MYSQL:
				return '`' . str_replace('`', '``', $identifier) . '`';
			case DB::DRIVER_MSSQL:
				return '[' . str_replace(['[', ']'], '', $identifier) . ']';
		}
		
		return '"' . str_replace('"', '\\"', $identifier) . '"';
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function applyPrefix($value)
	{
		if(ConnectionDecoratorChain::isDecorate())
		{
			return (new ConnectionDecoratorChain($this, $this->decorators))->applyPrefix($value);
		}
		
		return str_replace(DB::SCHEMA_OBJECT_PREFIX, '', $value);
	}
}
