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
				$sql = "DROP VIEW IF EXISTS " . implode(', ', array_map(function($view) {
					return $this->conn->quoteIdentifier($view);
				}, $views));
				$this->conn->execute($sql);
			}
			
			$stmt = $this->conn->prepare("SHOW TABLES");
			$stmt->execute();
			$tables = $stmt->fetchColumns(0);
			
			if(!empty($tables))
			{
				$sql = "DROP TABLE IF EXISTS " . implode(', ', array_map(function($table) {
					return $this->conn->quoteIdentifier($table);
				}, $tables));
				$this->conn->execute($sql);
			}
		}
		finally
		{
			$this->conn->execute("SET FOREIGN_KEY_CHECKS = 1");
		}
	}
	
	public function flushData()
	{
		$this->conn->execute("SET FOREIGN_KEY_CHECKS = 0");
		
		try
		{
			$stmt = $this->conn->prepare("SELECT `table_name` FROM `information_schema`.`TABLES` WHERE `table_name` NOT LIKE :kk AND `table_schema` = DATABASE()");
			$stmt->bindValue('kk', str_replace('_', '\\_', $this->conn->applyPrefix('#__kk_%')));
			$stmt->execute();
			
			foreach($stmt->fetchColumns(0) as $table)
			{
				$this->conn->execute("TRUNCATE TABLE " . $this->conn->quoteIdentifier($table));
			}
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
		$options = [
			'engine' => 'InnoDB',
			'collate' => 'utf8_unicode_ci'
		];
		$options = array_merge($options, array_change_key_case($table->getOptions(), CASE_LOWER));
		
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
		
		foreach($table->getPendingIndexes() as $index)
		{
			$cols = array_map(function($col) {
				return $this->conn->quoteIdentifier($col);
			}, $index->getColumns());
			
			$sql .= ', ' . $this->getIndexDefinitionSql($table, $index) . ' (' . implode(', ', $cols) . ')';
		}
		
		foreach($table->getPendingForeignKeys() as $key)
		{
			$sql .= ', ' . $this->getForeignKeyDefinitionSql($table->getName(), $key);
		}
		
		$sql .= ')';
		
		foreach($options as $k => $v)
		{
			$sql .= sprintf(' %s=%s', strtoupper($k), $v);
		}
		
		$stmt = $this->conn->prepare($sql);
		$stmt->execute();
	}
	
// 	public function renameTable($tableName, $newName)
// 	{
// 		$sql = sprintf("RENAME TABLE %s TO %s", $this->conn->quoteIdentifier($tableName), $this->conn->quoteIdentifier($newName));
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
		$name = $index->getName($this->conn->applyPrefix($tableName));
		
		$sql = sprintf('SHOW KEYS FROM %s WHERE Key_name = :name', $this->conn->quoteIdentifier($tableName));
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue('name', $name);
		$stmt->execute();
		
		if(count($stmt->fetchRows()))
		{
			$sql = sprintf('ALTER TABLE %s DROP INDEX %s', $this->conn->quoteIdentifier($tableName), $this->conn->quoteIdentifier($name));
			$stmt = $this->conn->prepare($sql);
			$stmt->execute();
		}
	}
	
	public function addForeignKey(Table $table, ForeignKey $key)
	{
		$sql = sprintf('ALTER TABLE %s ADD %s', $this->conn->quoteIdentifier($table->getName()), $this->getForeignKeyDefinitionSql($table->getName(), $key));
		$this->conn->execute($sql);
	}
	
	public function dropForeignKey($tableName, array $columns, $refTable, array $refColumns)
	{
		$key = new ForeignKey($columns, $refTable, $refColumns);
		$name = $key->getName($this->conn->applyPrefix($tableName));
		
		$sql = "
			SELECT 1
			FROM `information_schema`.`TABLE_CONSTRAINTS`
			WHERE `information_schema`.`TABLE_CONSTRAINTS`.`CONSTRAINT_TYPE` = :type
			AND `information_schema`.`TABLE_CONSTRAINTS`.`TABLE_SCHEMA` = DATABASE()
			AND `information_schema`.`TABLE_CONSTRAINTS`.`TABLE_NAME` = :table
			AND `information_schema`.`TABLE_CONSTRAINTS`.`CONSTRAINT_NAME` = :name
			LIMIT 1
		";
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue('type', 'FOREIGN KEY');
		$stmt->bindValue('table', $this->conn->applyPrefix($tableName));
		$stmt->bindValue('name', $name);
		$stmt->execute();
	
		if($stmt->fetchNextColumn(0))
		{
			$sql = sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $this->conn->quoteIdentifier($tableName), $this->conn->quoteIdentifier($name));
			$this->conn->execute($sql);
		}
	}
	
	protected function getDatabaseType($type)
	{
		switch($type)
		{
			case Column::TYPE_BIG_INT:
				return ['name' => 'bigint', 'unsigned' => false];
			case Column::TYPE_BINARY:
				return ['name' => 'binary', 'limit' => 250];
			case Column::TYPE_BLOB:
				return ['name' => 'longblob'];
			case Column::TYPE_BOOL:
				return ['name' => 'tinyint', 'limit' => 1, 'unsigned' => true];
			case Column::TYPE_CHAR:
				return ['name' => 'char', 'limit' => 250];
			case Column::TYPE_DOUBLE:
				return ['name' => 'double'];
			case Column::TYPE_INT:
				return ['name' => 'int', 'unsigned' => false];
			case Column::TYPE_TEXT:
				return ['name' => 'longtext'];
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
		
		if(!empty($type['unsigned']) || (array_key_exists('unsigned', $type) && $col->isUnsigned()))
		{
			$sql .= ' UNSIGNED';
		}
		
		$sql .= $col->isNullable() ? ' NULL' : ' NOT NULL';
		
		if($col->hasDefault())
		{
			$sql .= $this->getDefaultValueSql($col->getDefault());
		}
		
		if($col->isPrimaryKey() && $col->isIdentity())
		{
			$sql .= ' AUTO_INCREMENT';
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
