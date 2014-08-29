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
	protected $conn;
	
	protected function setUp()
	{
		parent::setUp();
		
		$dsn = (string)self::getEnvParam('DB_DSN', 'sqlite::memory:');
		$username = self::getEnvParam('DB_USERNAME', NULL);
		$password = self::getEnvParam('DB_PASSWORD', NULL);
		
		$pdo = new \PDO($dsn, $username, $password);
		$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		
		$this->conn = new PrefixConnectionDecorator(new Connection($pdo), 'db_');
		
		$ddl = str_replace('\\', '/', sprintf('%s/ConnectionTest.%s.sql', __DIR__, $this->conn->getDriverName()));
		$chunks = explode(';', file_get_contents($ddl));
			
		foreach($chunks as $chunk)
		{
			$sql = trim($chunk);
				
			if($sql === '')
			{
				continue;
			}
		
			$this->conn->execute($chunk);
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
		$stmt = $this->conn->prepare($sql);
		$stmt->bindValue('title', 'My Test Blog');
		$stmt->bindValue('date', $date->format('U'));
		$this->assertEquals(1, $stmt->execute());
		$this->assertEquals(1, $this->conn->lastInsertId());
		
		$sql = "	SELECT *
					FROM `#__blog`
		";
		$stmt = $this->conn->prepare($sql);
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
	
	public function testInsertAndUpsert()
	{
		$this->conn->insert('#__blog', [
			'title' => 'My Foo Blog',
			'created_at' => time()
		]);
		
		$blogId = $this->conn->lastInsertId();
		
		$this->conn->insert('#__post', [
			'blog_id' => $blogId,
			'title' => 'My first Post',
			'content' => 'Hello World :)',
			'created_at' => time()
		]);
		
		$postId = $this->conn->lastInsertId();
		
		$this->conn->insert('#__tag', [
			'name' => 'Test'
		]);
		
		$tagId = $this->conn->lastInsertId();
		
		$this->conn->upsert('#__post_tag', [
			'post_id' => $postId,
			'tag_id' => $tagId
		], []);
		
		$this->conn->upsert('#__post_tag', [
			'post_id' => $postId,
			'tag_id' => $tagId
		], []);
	}
}
