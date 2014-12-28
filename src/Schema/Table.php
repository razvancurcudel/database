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

use KoolKode\Database\Platform\AbstractPlatform;

class Table
{
	protected $name;
	
	protected $columns = [];
	
	protected $indexes = [];
	
	protected $foreignKeys = [];
	
	protected $platform;
	
	public function __construct($name, AbstractPlatform $platform)
	{
		$this->name = (string)$name;
		$this->platform = $platform;
	}
	
	public function getName()
	{
		return $this->name;
	}
	
	public function getPendingColumns()
	{
		return $this->columns;
	}
	
	public function getPendingIndexes()
	{
		return $this->indexes;
	}
	
	public function getPendingForeignKeys()
	{
		return $this->foreignKeys;
	}
	
	public function addColumn($name, $type, array $options = [])
	{
		$this->columns[] = new Column($name, $type, $options);
		
		return $this;
	}
	
	public function removeColumn($name)
	{
		return $this;
	}
	
	public function addIndex(array $columns, array $options = [])
	{
		$this->indexes[] = new Index($columns, $options);
		
		return $this;
	}
	
	public function removeIndex(array $columns)
	{
		$this->platform->dropIndex($this->name, $columns);
		
		return $this;
	}
	
	public function addForeignKey(array $columns, $refTable, array $refColumns, array $options = [])
	{
		$this->foreignKeys[] = new ForeignKey($columns, $refTable, $refColumns, $options);
		
		return $this;
	}
	
	public function removeForeignKey(array $columns)
	{
		
	}
	
	public function create()
	{
		$this->platform->createTable($this);
		
		$this->columns = [];
		$this->foreignKeys = [];
		$this->indexes = [];
		
		return $this;
	}
	
	public function update()
	{
		foreach($this->columns as $col)
		{
			$this->platform->addColumn($this, $col);
		}
		
		foreach($this->indexes as $index)
		{
			$this->platform->addIndex($this, $index);
		}
		
		foreach($this->foreignKeys as $key)
		{
			$this->platform->addForeignKey($this, $key);
		}
		
		$this->columns = [];
		$this->foreignKeys = [];
		$this->indexes = [];
		
		return $this;
	}
	
	public function save()
	{
		if($this->platform->hasTable($this->name))
		{
			return $this->update();
		}
		
		return $this->create();
	}
}
