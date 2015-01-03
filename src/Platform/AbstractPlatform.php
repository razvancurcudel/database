<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Platform;

use KoolKode\Database\ConnectionInterface;
use KoolKode\Database\Schema\Column;
use KoolKode\Database\Schema\ForeignKey;
use KoolKode\Database\Schema\Index;
use KoolKode\Database\Schema\Table;

abstract class AbstractPlatform
{
	/**
	 * 
	 * @var ConnectionInterface
	 */
	protected $conn;
	
	public function __construct(ConnectionInterface $conn)
	{
		$this->conn = $conn;
	}
	
	public function getConnection()
	{
		return $this->conn;
	}
	
	public function setConnection(ConnectionInterface $conn)
	{
		$this->conn = $conn;
	}
	
	public abstract function flushDatabase();

	public abstract function flushData();
	
	public abstract function hasTable($tableName);
	
	public abstract function createTable(Table $table);
	
// 	public abstract function renameTable($tableName, $newName);
	
	public abstract function dropTable($tableName);
	
	public abstract function addColumn(Table $table, Column $col);
	
	public abstract function addIndex(Table $table, Index $index);
	
	public abstract function dropIndex($tableName, array $columns);
	
	public abstract function addForeignKey(Table $table, ForeignKey $key);
	
	public abstract function dropForeignKey($tableName, array $columns, $refTable, array $refColumns);
}
