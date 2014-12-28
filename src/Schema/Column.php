<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Schema;

class Column
{
	const TYPE_VARCHAR = 'varchar';
	const TYPE_CHAR = 'char';
	const TYPE_TEXT = 'text';
	const TYPE_INT = 'int';
	const TYPE_BIG_INT = 'bigint';
	const TYPE_DOUBLE = 'double';
	const TYPE_BLOB = 'blob';
	
	protected $name;
	
	protected $type;
	
	protected $options;
	
	public function __construct($name, $type, array $options = [])
	{
		$this->name = (string)$name;
		$this->type = $this->assertType($type);
		$this->options = $options;
	}
	
	public function getName()
	{
		return $this->name;
	}
	
	public function getType()
	{
		return $this->type;
	}
	
	public function getOptions()
	{
		return $this->options;
	}
	
	public function getLimit()
	{
		return array_key_exists('limit', $this->options) ? (int)$this->options['limit'] : NULL;
	}
	
	public function isPrimaryKey()
	{
		return !empty($this->options['primary_key']) || !empty($this->options['identity']);
	}
	
	public function isIdentity()
	{
		return !empty($this->options['identity']);
	}
	
	public function isNullable()
	{
		return !empty($this->options['null']) || (array_key_exists('default', $this->options) && $this->options['default'] === NULL);
	}
	
	public function hasDefault()
	{
		return array_key_exists('default', $this->options);
	}
	
	public function getDefault()
	{
		return array_key_exists('default', $this->options) ? $this->options['default'] : NULL;
	}
	
	protected function assertType($type)
	{
		switch((string)$type)
		{
			case self::TYPE_BIG_INT:
			case self::TYPE_BLOB:
			case self::TYPE_CHAR:
			case self::TYPE_DOUBLE:
			case self::TYPE_INT:
			case self::TYPE_TEXT:
			case self::TYPE_VARCHAR:
				return (string)$type;
		}
		
		throw new \InvalidArgumentException(sprintf('Invalid column data type: "%s"', $type));
	}
}
