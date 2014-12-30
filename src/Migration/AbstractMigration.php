<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Migration;

use KoolKode\Database\ConnectionInterface;
use KoolKode\Database\Platform\AbstractPlatform;
use KoolKode\Database\Schema\Table;

abstract class AbstractMigration
{
	protected $version;
	
	protected $conn;
	
	protected $platform;
	
	public function __construct($version, ConnectionInterface $conn, AbstractPlatform $platform)
	{
		$this->version = (string)$version;
		$this->conn = $conn;
		$this->platform = $platform;
	}
	
	public function getVersion()
	{
		return $this->version;
	}
	
	public function up()
	{
		
	}
	
	public function down()
	{
		
	}
	
	public function hasTable($tableName)
	{
		return $this->platform->hasTable($tableName);
	}
	
	public function table($tableName, array $options = [])
	{
		return new Table($tableName, $this->platform, $options);
	}
	
	public function dropTable($tableName)
	{
		$this->platform->dropTable($tableName);
	}
	
	public function dropIndex($tableName, array $columns)
	{
		$this->platform->dropIndex($tableName, $columns);
	}
	
	public function dropForeignKey($tableName, array $columns, $refTable, array $refColumns)
	{
		$this->platform->dropForeignKey($tableName, $columns, $refTable, $refColumns);
	}
}
