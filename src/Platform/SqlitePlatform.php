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

use KoolKode\Database\Schema\Column;
use KoolKode\Database\Schema\ForeignKey;
use KoolKode\Database\Schema\Index;
use KoolKode\Database\Schema\Table;

class SqlitePlatform extends AbstractPlatform
{
	public function hasTable($tableName)
	{
		$tn = $this->conn->applyPrefix($tableName);
		
		$stmt = $this->conn->prepare("SELECT `name` FROM `sqlite_master` WHERE `type` = 'table' AND `name` = :name");
		$stmt->bindValue('name', $tn);
		$stmt->execute();

		$found = [];
		foreach($stmt->fetchRows() as $row)
		{
			$found[] = strtolower($row['name']);
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
		
		foreach($table->getPendingForeignKeys() as $key)
		{
			$sql .= ', ' . $this->getForeignKeyDefinitionSql($key);
		}
		
		$sql .= ') ';
		
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
		
		foreach($table->getPendingIndexes() as $index)
		{
			$this->createIndex($table, $index);
		}
	}
	
	public function renameTable($tableName, $newName)
	{
		$sql = sprintf("ALTER TABLE %s RENAME TO %s", $this->conn->quoteIdentifier($tableName), $this->conn->quoteIdentifier($newName));
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
			"ALTER TABLE %s ADD COLUMN %s %s",
			$this->conn->quoteIdentifier($table->getName()),
			$this->conn->quoteIdentifier($col->getName()),
			$this->getColumnDefinitionSql($col)
		);
		$this->conn->execute($sql);
	}
	
	public function createIndex(Table $table, Index $index)
	{
		$cols = array_map(function($col) {
			return $this->conn->quoteIdentifier($col);
		}, $index->getColumns());
		
		$sql = sprintf('CREATE %s ON %s (%s)', $this->getIndexDefinitionSql($table, $index), $this->conn->quoteIdentifier($table->getName()), \implode(', ', $cols));
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
	public function dropIndex($tableName, array $columns)
	{
		$index = new Index($columns);
		
		$sql = sprintf('DROP INDEX %s', $this->conn->quoteIdentifier($index->getName($tableName)));
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
	public function addForeignKey(Table $table, ForeignKey $key)
	{
		$stmt = $this->conn->prepare("SELECT `sql` FROM `sqlite_master` WHERE `type` = :type AND `tbl_name` = :name");
		$stmt->bindValue('type', 'table');
		$stmt->bindValue('name', $this->conn->applyPrefix($table->getName()));
		$stmt->execute();
		
		$tmpName = $table->getName() . '_tmp_';
		$sql = rtrim($stmt->fetchNextColumn('sql'), ' )');
		
		if('' === trim($sql))
		{
			throw new \RuntimeException(sprintf('Database table "%s" not found', $table->getName()));
		}
		
		$stmt = $this->conn->prepare(sprintf("PRAGMA table_info(%s)", $this->conn->quoteIdentifier($table->getName())));
		$stmt->execute();
		
		$this->conn->execute(sprintf('ALTER TABLE %s RENAME TO %s', $this->conn->quoteIdentifier($table->getName()), $this->conn->quoteIdentifier($tmpName)));
		
		$cols = array_map(function($val) { return $this->conn->quoteIdentifier($val); }, (array)$stmt->fetchColumns('name'));
		
		$this->conn->execute($sql . ', ' . $this->getForeignKeyDefinitionSql($key) . ')');
		
		$sql = sprintf(
			"INSERT INTO %s (%s) SELECT %s FROM %s",
			$this->conn->quoteIdentifier($table->getName()),
			implode(', ', $cols),
			implode(', ', $cols),
			$this->conn->quoteIdentifier($tmpName)
		);
		$this->conn->execute($sql);
		
		$this->conn->execute(sprintf("DROP TABLE %s", $this->conn->quoteIdentifier($tmpName)));
	}
	
	protected function getDatabaseType($type)
	{
		switch($type)
		{
			case Column::TYPE_BIG_INT:
				return ['name' => 'bigint'];
			case Column::TYPE_BLOB:
				return ['name' => 'blob'];
			case Column::TYPE_CHAR:
				return ['name' => 'char', 'limit' => 250];
			case Column::TYPE_DOUBLE:
				return ['name' => 'double'];
			case Column::TYPE_INT:
				return ['name' => 'integer'];
			case Column::TYPE_TEXT:
				return ['name' => 'text'];
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
				$sql .= ' AUTOINCREMENT';
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
