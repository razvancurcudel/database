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
	public function flushDatabase()
	{
		$this->conn->execute("PRAGMA foreign_keys = OFF");
		
		try
		{
			$stmt = $this->conn->prepare("SELECT `name` FROM `sqlite_master` WHERE `name` NOT GLOB 'sqlite_*' AND `type` = 'view'");
			$stmt->execute();
			
			foreach($stmt->fetchColumns(0) as $view)
			{
				$this->conn->execute("DROP VIEW " . $this->conn->quoteIdentifier($view));
			}
			
			$stmt = $this->conn->prepare("SELECT `name` FROM `sqlite_master` WHERE `name` NOT GLOB 'sqlite_*' AND `type` = 'table'");
			$stmt->execute();
			
			foreach($stmt->fetchColumns(0) as $table)
			{
				$this->conn->execute("DROP TABLE " . $this->conn->quoteIdentifier($table));
			}
		}
		finally
		{
			$this->conn->execute("PRAGMA foreign_keys = ON");
		}
	}
	
	public function flushData()
	{
		$this->conn->execute("PRAGMA foreign_keys = OFF");
		
		try
		{
			$stmt = $this->conn->prepare("SELECT `name` FROM `sqlite_master` WHERE `name` NOT GLOB 'sqlite_*' AND `name` NOT GLOB :kk AND `type` = 'table'");
			$stmt->bindValue('kk', $this->conn->applyPrefix('#__kk_*'));
			$stmt->execute();
			
			foreach($stmt->fetchColumns(0) as $table)
			{
				$this->conn->execute("DELETE FROM " . $this->conn->quoteIdentifier($table));
			}
		}
		finally
		{
			$this->conn->execute("PRAGMA foreign_keys = ON");
		}
	}
	
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
		$pk = [];
		$sql = 'CREATE TABLE ' . $this->conn->quoteIdentifier($table->getName()) . ' ( ';
		
		foreach($table->getPendingColumns() as $col)
		{
			$sql .= $this->conn->quoteIdentifier($col->getName()) . ' ' . $this->getColumnDefinitionSql($col) . ', ';
			
			if($col->isPrimaryKey() && !$col->isIdentity())
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
			$sql .= ', ' . $this->getForeignKeyDefinitionSql($key);
		}
		
		$sql .= ') ';
		
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
	
	public function addIndex(Table $table, Index $index)
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
		
		$sql = sprintf('DROP INDEX %s', $this->conn->quoteIdentifier($index->getName($this->conn->applyPrefix($tableName))));
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
		$cols = array_map(function($val) { return $this->conn->quoteIdentifier($val); }, (array)$stmt->fetchColumns('name'));
		
		$stmt = $this->conn->prepare("SELECT `sql` FROM `sqlite_master` WHERE `type` = :type AND `tbl_name` = :name AND NOT `sql` IS NULL");
		$stmt->bindValue('type', 'index');
		$stmt->bindValue('name', $this->conn->applyPrefix($table->getName()));
		$stmt->execute();
		$idx = $stmt->fetchColumns(0);
		
		$this->conn->execute(sprintf('ALTER TABLE %s RENAME TO %s', $this->conn->quoteIdentifier($table->getName()), $this->conn->quoteIdentifier($tmpName)));
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
		
		foreach($idx as $sql)
		{
			$this->conn->execute($sql);
		}
	}
	
	public function dropForeignKey($tableName, array $columns, $refTable, array $refColumns)
	{
		$stmt = $this->conn->prepare("SELECT `sql` FROM `sqlite_master` WHERE `type` = :type AND `tbl_name` = :name");
		$stmt->bindValue('type', 'table');
		$stmt->bindValue('name', $this->conn->applyPrefix($tableName));
		$stmt->execute();
		
		$tmpName = $tableName . '_tmp_';
		$sql = rtrim($stmt->fetchNextColumn('sql'), ' )');
		
		if('' === trim($sql))
		{
			throw new \RuntimeException(sprintf('Database table "%s" not found', $tableName));
		}
		
		$stmt = $this->conn->prepare(sprintf("PRAGMA table_info(%s)", $this->conn->quoteIdentifier($tableName)));
		$stmt->execute();
		
		$cols = [];
		$check = array_fill_keys($columns, true);
		
		foreach($stmt->fetchRows() as $row)
		{
			$cols[] = $this->conn->quoteIdentifier($row['name']);
			
			if(isset($check[$row['name']]))
			{
				unset($check[$row['name']]);
			}
		}
		
		if(!empty($check))
		{
			throw new \RuntimeException(sprintf('Column(s) %s not found in table %s', implode(', ', array_keys($check)), $tableName));
		}
		
		$stmt = $this->conn->prepare("SELECT `sql` FROM `sqlite_master` WHERE `type` = :type AND `tbl_name` = :name AND NOT `sql` IS NULL");
		$stmt->bindValue('type', 'index');
		$stmt->bindValue('name', $this->conn->applyPrefix($tableName));
		$stmt->execute();
		$idx = $stmt->fetchColumns(0);
		
		$this->conn->execute(sprintf("ALTER TABLE %s RENAME TO %s", $this->conn->quoteIdentifier($tableName), $this->conn->quoteIdentifier($tmpName)));
		
		// Look for foreign key definitions in existing create table DDL:
		$m = NULL;
		
		if(preg_match_all("',\s*FOREIGN\s+KEY\s*\\(([^\\)]+)\\)\s*REFERENCES\s+([^\\(]+)\s*\\(([^\\)]+)\\)[^,$]*'i", $sql, $m, PREG_SET_ORDER))
		{
			$cleanup = function($val) { return trim(trim($val), '"'); };
			
			foreach($m as $fk)
			{
				$fcols = array_map($cleanup, explode(',', $fk[1]));
				$frcols = array_map($cleanup, explode(',', $fk[3]));
				
				if($cleanup($fk[2]) == $this->conn->applyPrefix($refTable) && $fcols == $columns && $frcols == $refColumns)
				{
					$sql = str_replace($fk[0], ' ', $sql);
				}
			}
		}
		
		$this->conn->execute($sql . ')');
		
		$sql = sprintf(
			'INSERT INTO %s (%s) SELECT %s FROM %s',
			$this->conn->quoteIdentifier($tableName),
			implode(', ', $cols),
			implode(', ', $cols),
			$this->conn->quoteIdentifier($tmpName)
		);
		$this->conn->execute($sql);
		$this->conn->execute(sprintf("DROP TABLE %s", $this->conn->quoteIdentifier($tmpName)));
		
		foreach($idx as $sql)
		{
			$this->conn->execute($sql);
		}
	}
	
	protected function getDatabaseType($type)
	{
		switch($type)
		{
			case Column::TYPE_BIG_INT:
				return ['name' => 'integer'];
			case Column::TYPE_BINARY:
				return ['name' => 'binary', 'limit' => 250];
			case Column::TYPE_BLOB:
				return ['name' => 'blob'];
			case Column::TYPE_BOOL:
				return ['name' => 'tinyint', 'unsigned' => true, 'limit' => 1];
			case Column::TYPE_CHAR:
				return ['name' => 'char', 'limit' => 250];
			case Column::TYPE_DOUBLE:
				return ['name' => 'double'];
			case Column::TYPE_INT:
				return ['name' => 'integer'];
			case Column::TYPE_TEXT:
				return ['name' => 'text'];
			case Column::TYPE_UUID:
				return ['name' => 'binary', 'limit' => 16];
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
		
		if(array_key_exists('limit', $type))
		{
			$sql .= sprintf('(%u)', ($limit === NULL) ? $type['limit'] : min($limit, $type['limit']));
		}
		
		$sql .= $col->isNullable() ? ' NULL' : ' NOT NULL';
		
		if($col->hasDefault())
		{
			$sql .= $this->getDefaultValueSql($col->getDefault());
		}
		
		if($col->isPrimaryKey() && $col->isIdentity())
		{
			$sql .= ' PRIMARY KEY AUTOINCREMENT';
		}
		
		return $sql;
	}
	
	protected function getIndexDefinitionSql(Table $table, Index $index)
	{
		$sql = $index->isUnique() ? 'UNIQUE INDEX ' : 'INDEX ';
		$sql .= $this->conn->quoteIdentifier($index->getName($this->conn->applyPrefix($table->getName())));
		
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
