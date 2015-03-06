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
	public function applyPrefix($value)
	{
		return str_replace(DB::SCHEMA_OBJECT_PREFIX, $this->prefix, $value);
	}
}
