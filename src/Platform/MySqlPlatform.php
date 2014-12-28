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

use KoolKode\Database\DB;
use KoolKode\Database\Schema\Column;
use KoolKode\Database\Schema\ForeignKey;
use KoolKode\Database\Schema\Index;
use KoolKode\Database\Schema\Table;

class MySqlPlatform extends AbstractPlatform
{
	public function flushDatabase()
	{
		$this->conn->execute("SET FOREIGN_KEY_CHECKS = 0");
		
		try
		{
			$stmt = $this->conn->prepare("SHOW FULL TABLES WHERE TABLE_TYPE LIKE :type");
			$stmt->bindValue('type', 'VIEW');
			$stmt->execute();
			$views = $stmt->fetchColumns(0);

			if(!empty($views))
			{
				$sql = "DROP VIEW " . implode(', ', array_map(function($view) {
					return $this->conn->quoteIdentifier($view);
				}, $views));
				$this->conn->execute($sql);
			}
			
			$stmt = $this->conn->prepare("SHOW TABLES");
			$stmt->execute();
			$tables = $stmt->fetchColumns(0);
			
			if(!empty($tables))
			{
				$sql = "DROP TABLE " . implode(', ', array_map(function($table) {
					return $this->conn->quoteIdentifier($table);
				}, $tables));
				$this->conn->execute($sql);
			}
			
			// TODO: PostgreSQL needs this query instead of key checks config: DROP TABLE t1, t2, t3, ... CASCADE
		}
		finally
		{
			$this->conn->execute("SET FOREIGN_KEY_CHECKS = 1");
		}
	}
	
	public function hasTable($tableName)
	{
		$tn = $this->conn->applyPrefix($tableName);
		
		$stmt = $this->conn->prepare("SHOW TABLES");
		$stmt->execute();

		$found = [];
		foreach($stmt->fetchRows(DB::FETCH_NUM) as $row)
		{
			$found[] = strtolower($row[0]);
		}
		
		return in_array(strtolower($tn), $found);
	}

	public function createTable(Table $table)
	{
		$sql = 'CREATE TABLE ' . $this->conn->quoteIdentifier($table->getName()) . ' ( ';
		
		foreach($table->getPendingColumns() as $col)
		{
			$sql .= $this->conn->quoteIdentifier($col->getName()) . ' ' . $this->getColumnDefinitionSql($col) . ', ';
		}
		
		$sql = rtrim($sql, ', ');
		
		foreach($table->getPendingIndexes() as $index)
		{
			$cols = array_map(function($col) {
				return $this->conn->quoteIdentifier($col);
			}, $index->getColumns());
			
			$sql .= ', ' . $this->getIndexDefinitionSql($table, $index) . ' (' . implode(', ', $cols) . ')';
		}
		
		foreach($table->getPendingForeignKeys() as $key)
		{
			$sql .= ', ' . $this->getForeignKeyDefinitionSql($key);
		}
		
		$sql .= ') ENGINE=InnoDB COLLATE=utf8_unicode_ci';
		
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
	public function renameTable($tableName, $newName)
	{
		$sql = sprintf("RENAME TABLE %s TO %s", $this->conn->quoteIdentifier($tableName), $this->conn->quoteIdentifier($newName));
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
	public function dropTable($tableName)
	{
		$sql = sprintf('DROP TABLE %s', $this->conn->quoteIdentifier($tableName));
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
	public function addColumn(Table $table, Column $col)
	{
		$sql = sprintf(
			"ALTER TABLE %s ADD %s %s",
			$this->conn->quoteIdentifier($table->getName()),
			$this->conn->quoteIdentifier($col->getName()),
			$this->getColumnDefinitionSql($col)
		);
		$this->conn->execute($sql);
	}
	
	public function addIndex(Table $table, Index $index)
	{
		$cols = array_map(function($col) {
			return $this->conn->quoteIdentifier($col);
		}, $index->getColumns());
		
		$sql = sprintf('ALTER TABLE %s ADD %s (%s)', $this->conn->quoteIdentifier($table->getName()), $this->getIndexDefinitionSql($table, $index), implode(', ', $cols));
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
	public function dropIndex($tableName, array $columns)
	{
		$index = new Index($columns);
		
		$sql = sprintf('ALTER TABLE %s DROP INDEX %s', $this->conn->quoteIdentifier($tableName), $this->conn->quoteIdentifier($index->getName($tableName)));
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
	public function addForeignKey(Table $table, ForeignKey $key)
	{
		$sql = sprintf('ALTER TABLE %s ADD %s', $this->conn->quoteIdentifier($table->getName()), $this->getForeignKeyDefinitionSql($key));
		$this->conn->execute($sql);
	}
	
	protected function getDatabaseType($type)
	{
		switch($type)
		{
			case Column::TYPE_BIG_INT:
				return ['name' => 'bigint', 'unsigned' => false];
			case Column::TYPE_BLOB:
				return ['name' => 'longblob'];
			case Column::TYPE_CHAR:
				return ['name' => 'char', 'limit' => 250];
			case Column::TYPE_DOUBLE:
				return ['name' => 'double'];
			case Column::TYPE_INT:
				return ['name' => 'int', 'unsigned' => false];
			case Column::TYPE_TEXT:
				return ['name' => 'longtext'];
			case Column::TYPE_VARCHAR:
				return ['name' => 'varchar', 'limit' => 250];
		}
	}
	
	protected function getDefaultValueSql($default)
	{
		if(is_string($default))
		{
			$default = $this->conn->quote($default);
		}
		elseif(is_bool($default))
		{
			$default = (int)$default;
		}
		
		return ('' !== trim($default)) ? (' DEFAULT ' . $default) : '';
	}
	
	protected function getColumnDefinitionSql(Column $col)
	{
		$type = $this->getDatabaseType($col->getType());
		$limit = $col->getLimit();
		
		$sql = strtoupper($type['name']);
		
		if(NULL !== $limit || array_key_exists('limit', $type))
		{
			$sql .= sprintf('(%u)', ($limit === NULL) ? $type['limit'] : min($limit, $type['limit']));
		}
		
		if(array_key_exists('unsigned', $type) && $col->isUnsigned())
		{
			$sql .= ' UNSIGNED';
		}
		
		$sql .= $col->isNullable() ? ' NULL' : ' NOT NULL';
		
		if($col->hasDefault())
		{
			$sql .= $this->getDefaultValueSql($col->getDefault());
		}
		
		if($col->isPrimaryKey())
		{
			$sql .= ' PRIMARY KEY';
			
			if($col->isIdentity())
			{
				$sql .= ' AUTO_INCREMENT';
			}
		}
		
		return $sql;
	}
	
	protected function getIndexDefinitionSql(Table $table, Index $index)
	{
		$sql = $index->isUnique() ? 'UNIQUE INDEX ' : 'INDEX ';
		$sql .= $this->conn->quoteIdentifier($index->getName($table->getName()));
		
		return $sql;
	}
	
	protected function getForeignKeyDefinitionSql(ForeignKey $key)
	{
		$quote = function($val) { return $this->conn->quoteIdentifier($val); };
		$cols = array_map($quote, $key->getColumns());
		$ref = array_map($quote, $key->getRefColumns());
		
		$sql = 'FOREIGN KEY (' . implode(', ', $cols) . ') REFERENCES ';
		$sql .= $this->conn->quoteIdentifier($key->getRefTable());
		$sql .= ' (' . implode(', ', $ref) . ')';
		
		if(NULL !== ($update = $key->getOnUpdate()))
		{
			$sql .= ' ON UPDATE ' . $update;
		}
		
		if(NULL !== ($delete = $key->getOnDelete()))
		{
			$sql .= ' ON DELETE ' . $delete;
		}
		
		return $sql;
	}
}