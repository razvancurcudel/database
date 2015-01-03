<?php

namespace KoolKode\Database\Migration;

use KoolKode\Database\Test\DatabaseTestTrait;

class MigrationManagerTest extends \PHPUnit_Framework_TestCase
{
	use DatabaseTestTrait;
	
	public function test1()
	{
		$conn = static::createConnection('mt_');
		
		$platform = $conn->getPlatform();
		
		$manager = new MigrationManager($conn);
		$manager->migrateDirectoryUp(__DIR__ . '/../../src/Migration');
		
		$this->assertTrue($platform->hasTable('#__kk_migrations'));
		$this->assertTrue($platform->hasTable('#__test1'));
	}
	
	public function test2()
	{
		$conn = static::createConnection('mt_');
	
		$platform = $conn->getPlatform();
	
		$manager = new MigrationManager($conn);
		$manager->migrateDirectoryUp(__DIR__ . '/../../src/Migration');
	
		$this->assertTrue($platform->hasTable('#__kk_migrations'));
		$this->assertTrue($platform->hasTable('#__test1'));
	}
}
