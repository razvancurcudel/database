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

class ForeignKey
{
	protected $columns;
	
	protected $refTable;
	
	protected $refColumns;
	
	protected $options;
	
	public function __construct(array $columns, $refTable, array $refColumns, array $options = [])
	{
		$this->columns = $columns;
		$this->refTable = (string)$refTable;
		$this->refColumns = $refColumns;
		$this->options = $options;
	}
	
	public function getName($tableName)
	{
		return 'idx_' . sha1($tableName . '||' . \implode(',', $this->columns) . '||' . $this->refTable . '||' . \implode(',', $this->refColumns));
	}
	
	public function getColumns()
	{
		return $this->columns;
	}
	
	public function getRefTable()
	{
		return $this->refTable;
	}
	
	public function getRefColumns()
	{
		return $this->refColumns;
	}
	
	public function getOnUpdate()
	{
		return isset($this->options['update']) ? strtoupper($this->options['update']) : 'CASCADE';
	}
	
	public function getOnDelete()
	{
		return isset($this->options['delete']) ? strtoupper($this->options['delete']) : 'CASCADE';		
	}
}
