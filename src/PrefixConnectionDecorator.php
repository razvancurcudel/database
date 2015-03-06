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
 * Connection decorator that applies a schema object prefix to SQL queries.
 * 
 * @author Martin Schröder
 */
class PrefixConnectionDecorator extends ConnectionDecorator
{
	/**
	 * Schema object prefix to be used.
	 * 
	 * @var string
	 */
	protected $prefix;
	
	/**
	 * Wrap an existing connection using a schema object prefix decorator.
	 * 
	 * @param string $prefix
	 */
	public function __construct($prefix)
	{
		$this->prefix = (string)$prefix;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute($sql, $prefix = NULL)
	{
		return $this->conn->execute($sql, ($prefix === NULL) ? $this->prefix : $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function prepare($sql, $prefix = NULL)
	{
		return $this->conn->prepare($sql, ($prefix === NULL) ? $this->prefix : $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function insert($tableName, array $values, $prefix = NULL)
	{
		return $this->conn->insert($tableName, $values, ($prefix === NULL) ? $this->prefix : $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function upsert($tableName, array $key, array $values, $prefix = NULL)
	{
		return $this->conn->upsert($tableName, $key, $values, ($prefix === NULL) ? $this->prefix : $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function update($tableName, array $key, array $values, $prefix = NULL)
	{
		return $this->conn->update($tableName, $key, $values, ($prefix === NULL) ? $this->prefix : $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function delete($tableName, array $key, $prefix = NULL)
	{
		return $this->conn->delete($tableName, $key, ($prefix === NULL) ? $this->prefix : $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function lastInsertId($sequenceName, $prefix = NULL)
	{
		return $this->conn->lastInsertId($sequenceName, ($prefix === NULL) ? $this->prefix : $prefix);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function applyPrefix($value, $prefix = NULL)
	{
		return $this->conn->applyPrefix($value, ($prefix === NULL) ? $this->prefix : $prefix);
	}
}
