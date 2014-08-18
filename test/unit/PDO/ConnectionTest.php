<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\PDO;

use KoolKode\Database\DB;
use KoolKode\Database\Test\DatabaseTestCase;
use KoolKode\Database\PrefixConnectionDecorator;

class ConnectionTest extends DatabaseTestCase
{
	protected static $conn;
	
	public static function setUpBeforeClass()
	{
		parent::setUpBeforeClass();
		
		if(self::$conn !== NULL)
		{
			return;
		}
		
		$dsn = (string)self::getEnvParam('DB_DSN', 'sqlite::memory:');
		$username = self::getEnvParam('DB_USERNAME', NULL);
		$password = self::getEnvParam('DB_PASSWORD', NULL);
		
		printf("DB: \"%s\"\n", $dsn);
		
		$pdo = new \PDO($dsn, $username, $password);
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		
		self::$conn = new PrefixConnectionDecorator(new Connection($pdo), 'test_');
		
		switch(self::$conn->getDriverName())
		{
			case DB::DRIVER_SQLITE:
				self::$conn->execute("PRAGMA foreign_keys = ON");
				break;
			case DB::DRIVER_MYSQL:
				self::$conn->execute("SET NAMES 'utf8'");
				break;
		}
		
		$ddl = str_replace('\\', '/', sprintf('%s/ConnectionTest.%s.sql', __DIR__, self::$conn->getDriverName()));
		$chunks = explode(';', file_get_contents($ddl));
		
		printf("DDL: \"%s\"\n\n", $ddl);
			
		foreach($chunks as $chunk)
		{
			$sql = trim($chunk);
				
			if($sql === '')
			{
				continue;
			}
		
			self::$conn->execute($chunk);
		}
	}
	
	protected function setUp()
	{
		parent::setUp();
		
		$this->clearTables();
	}
	
	protected function tearDown()
	{
		$this->clearTables();
	
		parent::tearDown();
	}
	
	protected function clearTables()
	{
		static $tables = [
			'#__post_tag',
			'#__tag',
			'#__post',
			'#__blog'
		];
		
		foreach($tables as $table)
		{
			self::$conn->prepare("DELETE FROM `$table`")->execute();
		}
	}
	
	public function testBasicInsertAndSelect()
	{
		$date = new \DateTime('2014-07-25T13:45:21Z');
		
		$sql = "	INSERT INTO `#__blog`
						(`title`, `created_at`)
					VALUES
						(:title, :date)
		";
		$stmt = self::$conn->prepare($sql);
		$stmt->bindValue('title', 'My Test Blog');
		$stmt->bindValue('date', $date->format('U'));
		$this->assertEquals(1, $stmt->execute());
		$this->assertEquals(1, self::$conn->lastInsertId());
		
		$sql = "	SELECT *
					FROM `#__blog`
		";
		$stmt = self::$conn->prepare($sql);
		$stmt->transform('created_at', function($value) {
			return new \DateTime('@' . $value);
		});
		$stmt->compute('slug', function(array $row) {
			return trim(preg_replace("'[-\s]+'", '-', strtolower($row['title'])));
		});
		$stmt->execute();
		$rows = $stmt->fetchRows(DB::FETCH_ASSOC);
		$this->assertCount(1, $rows);
		
		$row = array_pop($rows);
		$this->assertTrue(is_array($row));
		$this->assertEquals(1, $row['id']);
		$this->assertEquals('My Test Blog', $row['title']);
		$this->assertEquals($date, $row['created_at']);
		$this->assertEquals('my-test-blog', $row['slug']);
	}
}
