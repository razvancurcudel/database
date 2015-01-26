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
use KoolKode\Database\Schema\Table;
use KoolKode\Database\Test\DatabaseTestTrait;
use KoolKode\Util\UUID;

class PlatformTest extends \PHPUnit_Framework_TestCase
{
	use DatabaseTestTrait;
	
	/**
	 * @var ConnectionInterface
	 */
	protected $conn;
	
	/**
	 * @var AbstractPlatform
	 */
	protected $platform;
	
	protected function setUp()
	{
		parent::setUp();
		
		$this->conn = static::createConnection('tt_');
		
		$this->platform = $this->conn->getPlatform();
		$this->platform->flushDatabase();
	}
	
	protected function tearDown()
	{
		if($this->platform instanceof AbstractPlatform)
		{
			$this->platform->flushData();
			$this->platform->flushDatabase();
		}
		
		parent::tearDown();
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
		
		$this->platform->dropForeignKey('#__test1', ['t2_id'], '#__test2', ['id']);
		$this->platform->dropIndex('#__test1', ['t2_id']);

		$this->conn->insert('#__test1', ['title' => 'foo', 't2_id' => 12]);
		
		$this->platform->dropTable('#__test1');
		$this->platform->dropTable('#__test2');
	}
	
	public function testUuidColumn()
	{
		$test = new Table('#__test', $this->platform);
		$test->addColumn('id', Column::TYPE_UUID, ['primary_key' => true]);
		$test->addColumn('title', Column::TYPE_VARCHAR);
		$test->save();
		
		$uuid = UUID::createRandom();
		
		$this->conn->insert('#__test', [
			'id' => $uuid,
			'title' => 'Entry 1'
		]);
		
		$stmt = $this->conn->prepare("SELECT * FROM `#__test` WHERE `id` = :id");
		$stmt->bindValue('id', $uuid);
		$stmt->execute();
		$row = $stmt->fetchNextRow();
		
		$this->assertEquals('Entry 1', $row['title']);
		$this->assertEquals($uuid, new UUID($row['id']));
	}
}
