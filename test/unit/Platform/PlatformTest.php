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
use KoolKode\Database\ConnectionManager;
use KoolKode\Database\DB;
use KoolKode\Database\Schema\Table;
use KoolKode\Database\Test\DatabaseTestCase;

class PlatformTest extends DatabaseTestCase
{
	protected $conn;
	
	protected $platform;
	
	protected function setUp()
	{
		parent::setUp();
		
		$dsn = (string)self::getEnvParam('DB_DSN', 'sqlite::memory:');
		$username = self::getEnvParam('DB_USERNAME', NULL);
		$password = self::getEnvParam('DB_PASSWORD', NULL);
	
		$this->conn = (new ConnectionManager())->createPDOConnection($dsn, $username, $password);
		$this->platform = $this->createPlatform($this->conn);
		
		$this->platform->flushDatabase();
	}
	
	protected function tearDown()
	{
		$this->platform->flushDatabase();
		
		parent::tearDown();
	}
	
	protected function createPlatform(ConnectionInterface $conn)
	{
		switch($conn->getDriverName())
		{
			case DB::DRIVER_MYSQL:
				return new MySqlPlatform($conn);
			case DB::DRIVER_SQLITE:
				return new SqlitePlatform($conn);
		}
		
		$this->fail('No platform available for DB driver: ' . $conn->getDriverName());
	}
	
	public function testTableCreation()
	{
		$this->assertFalse($this->platform->hasTable('#__test1'));
		
		$test1 = new Table('#__test1', $this->platform);
		$test1->addColumn('id', 'int', ['identity' => true]);
		$test1->addColumn('title', 'varchar');
		$test1->addIndex(['title']);
		
		$test2 = new Table('#__test2', $this->platform);
		$test2->addColumn('id', 'int', ['identity' => true]);
		
		$test1->save();
		$test2->save();
		
		$this->assertTrue($this->platform->hasTable('#__test1'));
		$this->assertTrue($this->platform->hasTable('#__test2'));
		
		$test1->addColumn('t2_id', 'int', ['default' => NULL]);
		$test1->addIndex(['t2_id']);
		$test1->addForeignKey(['t2_id'], '#__test2', ['id']);
		$test1->save();
		
		$this->platform->dropTable('#__test1');
		$this->platform->dropTable('#__test2');
	}
}
