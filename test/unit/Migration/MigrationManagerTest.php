<?php

namespace KoolKode\Database\Migration;

use KoolKode\Database\Test\DatabaseTestTrait;
use KoolKode\Database\StreamTransformer;
use KoolKode\Database\StringTransformer;
use KoolKode\Database\UUIDTransformer;
use KoolKode\Stream\StreamInterface;
use KoolKode\Stream\UrlStream;
use KoolKode\Util\UUID;

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
	
	public function testUuidTable()
	{
		$conn = static::createConnection('mt_');
		
		$manager = new MigrationManager($conn);
		$manager->migrateDirectoryUp(__DIR__ . '/../../src/Migration');
	
		$uuid = UUID::createRandom();
		$data = ['id' => $uuid, 'label' => 'Test entry #1', 'data' => new UrlStream(__FILE__, 'rb')];
		
		$conn->insert('#__test2', $data);
		
		$stmt = $conn->prepare("SELECT * FROM `#__test2` WHERE `id` = :id");
		$stmt->bindValue('id', $uuid);
		$stmt->transform('id', new UUIDTransformer());
		$stmt->transform('data', new StreamTransformer());
		$stmt->execute();
		
		$row = $stmt->fetchNextRow();
		$this->assertEquals($uuid, $row['id']);
		$this->assertEquals('Test entry #1', $row['label']);
		$this->assertTrue($row['data'] instanceof StreamInterface);
		$this->assertEquals(file_get_contents(__FILE__), $row['data']->getContents());
		
		$stmt = $conn->prepare("SELECT * FROM `#__test2` WHERE `id` = :id");
		$stmt->bindValue('id', $uuid);
		$stmt->transform('id', new UUIDTransformer());
		$stmt->transform('data', new StringTransformer());
		$stmt->execute();
		
		$row = $stmt->fetchNextRow();
		$this->assertEquals($uuid, $row['id']);
		$this->assertEquals('Test entry #1', $row['label']);
		$this->assertEquals(file_get_contents(__FILE__), $row['data']);
	}
}
