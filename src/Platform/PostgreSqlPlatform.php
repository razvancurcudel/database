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

class PostgreSqlPlatform extends AbstractPlatform
{
	public function flushDatabase()
	{
		$stmt = $this->conn->prepare("SELECT `table_name` FROM `information_schema`.`views` WHERE `table_schema` = current_schema()");
		$stmt->execute();
		$views = $stmt->fetchColumns(0);
		
		if(!empty($views))
		{
			$sql = "DROP VIEW IF EXISTS " . implode(', ', array_map(function($view) {
				return $this->conn->quoteIdentifier($view);
			}, $views)) . ' CASCADE';
			$this->conn->execute($sql);
		}
		
		$stmt = $this->conn->prepare("SELECT `table_name` FROM `information_schema`.`tables` WHERE `table_schema` = current_schema()");
		$stmt->execute();
		$tables = $stmt->fetchColumns(0);
			
		if(!empty($tables))
		{
			$sql = "DROP TABLE IF EXISTS " . implode(', ', array_map(function($table) {
				return $this->conn->quoteIdentifier($table);
			}, $tables)) . ' CASCADE';
			$this->conn->execute($sql);
		}
	}
	
	public function hasTable($tableName)
	{
		$tn = $this->conn->applyPrefix($tableName);
		
		$stmt = $this->conn->prepare("SELECT `table_name` FROM `information_schema`.`tables` WHERE `table_schema` = current_schema()");
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
		$pk = [];
		$sql = 'CREATE TABLE ' . $this->conn->quoteIdentifier($table->getName()) . ' ( ';
		
		foreach($table->getPendingColumns() as $col)
		{
			$sql .= $this->conn->quoteIdentifier($col->getName()) . ' ' . $this->getColumnDefinitionSql($col) . ', ';
			
			if($col->isPrimaryKey())
			{
				$pk[] = $this->conn->quoteIdentifier($col->getName());
			}
		}
		
		$sql = rtrim($sql, ', ');
		
		if(!empty($pk))
		{
			$sql .= sprintf(', PRIMARY KEY (%s)', implode(', ', $pk));
		}
		
		foreach($table->getPendingForeignKeys() as $key)
		{
			$sql .= ', ' . $this->getForeignKeyDefinitionSql($table->getName(), $key);
		}
		
		$sql .= ')';
		
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		
		foreach($table->getPendingIndexes() as $index)
		{
			$this->addIndex($table, $index);
		}
	}
	
// 	public function renameTable($tableName, $newName)
// 	{
// 		$sql = sprintf("ALTER TABLE %s RENAME TO %s", $this->conn->quoteIdentifier($tableName), $this->conn->quoteIdentifier($newName));
// 		$stmt = $this->conn->prepare($sql);
// 		$stmt->execute();
// 	}
	
	public function dropTable($tableName)
	{
		$sql = sprintf('DROP TABLE %s CASCADE', $this->conn->quoteIdentifier($tableName));
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
		
		$sql = sprintf(
			'CREATE%s INDEX %s ON %s (%s)',
			$index->isUnique() ? ' UNIQUE' : '',
			$this->conn->quoteIdentifier($index->getName($this->conn->applyPrefix($table->getName()))),
			$this->conn->quoteIdentifier($table->getName()),
			implode(', ', $cols)
		);
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
	public function dropIndex($tableName, array $columns)
	{
		$index = new Index($columns);
		
		$sql = sprintf('DROP INDEX IF EXISTS %s', $this->conn->quoteIdentifier($index->getName($this->conn->applyPrefix($tableName))));
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
	public function addForeignKey(Table $table, ForeignKey $key)
	{
		$sql = sprintf('ALTER TABLE %s ADD %s', $this->conn->quoteIdentifier($table->getName()), $this->getForeignKeyDefinitionSql($table->getName(), $key));
		$this->conn->execute($sql);
	}
	
	public function dropForeignKey($tableName, array $columns, $refTable, array $refColumns)
	{
		$key = new ForeignKey($columns, $refTable, $refColumns);
		
		$sql = sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $this->conn->quoteIdentifier($tableName), $this->conn->quoteIdentifier($key->getName($this->conn->applyPrefix($tableName))));
		$this->conn->execute($sql);
	}
	
	protected function getDatabaseType($type)
	{
		switch($type)
		{
			case Column::TYPE_BIG_INT:
				return ['name' => 'bigint', 'unsigned' => false];
			case Column::TYPE_BINARY:
				return ['name' => 'bytea'];
			case Column::TYPE_BLOB:
				return ['name' => 'bytea'];
			case Column::TYPE_CHAR:
				return ['name' => 'char', 'limit' => 250];
			case Column::TYPE_DOUBLE:
				return ['name' => 'double precision'];
			case Column::TYPE_INT:
				return ['name' => 'integer', 'unsigned' => false];
			case Column::TYPE_TEXT:
				return ['name' => 'text'];
			case Column::TYPE_UUID:
				return ['name' => 'uuid'];
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
		
		if($col->isIdentity())
		{
			$sql = 'SERIAL';
		}
		else
		{
			$sql = strtoupper($type['name']);
			
			if(array_key_exists('limit', $type))
			{
				$sql .= sprintf('(%u)', ($limit === NULL) ? $type['limit'] : min($limit, $type['limit']));
			}
			
			if(array_key_exists('unsigned', $type) && $col->isUnsigned())
			{
				$sql .= sprintf(' CHECK (%s >= 0)', $this->conn->quoteIdentifier($col->getName()));
			}
		}
		
		$sql .= $col->isNullable() ? ' NULL' : ' NOT NULL';
		
		if($col->hasDefault())
		{
			$sql .= $this->getDefaultValueSql($col->getDefault());
		}
		
		return $sql;
	}
	
	protected function getIndexDefinitionSql(Table $table, Index $index)
	{
		$sql = $index->isUnique() ? 'UNIQUE INDEX ' : 'INDEX ';
		$sql .= $this->conn->quoteIdentifier($index->getName($this->conn->applyPrefix($table->getName())));
		
		return $sql;
	}
	
	protected function getForeignKeyDefinitionSql($tableName, ForeignKey $key)
	{
		$quote = function($val) { return $this->conn->quoteIdentifier($val); };
		$cols = array_map($quote, $key->getColumns());
		$ref = array_map($quote, $key->getRefColumns());
		
		$sql = 'CONSTRAINT ' . $this->conn->quoteIdentifier($key->getName($this->conn->applyPrefix($tableName)));
		$sql .= ' FOREIGN KEY (' . implode(', ', $cols) . ') REFERENCES ';
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
