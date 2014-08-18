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
	 * @param ConnectionInterface $conn
	 * @param string $prefix
	 */
	public function __construct(ConnectionInterface $conn, $prefix)
	{
		parent::__construct($conn);
		
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
}
