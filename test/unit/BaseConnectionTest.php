<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database;

use KoolKode\Database\Test\DatabaseTestTrait;

abstract class BaseConnectionTest extends \PHPUnit_Framework_TestCase
{
	use DatabaseTestTrait;
	
	/**
	 * DB connection being tested.
	 * 
	 * @var ConnectionInterface
	 */
	protected $conn;
	
	protected function setUp()
	{
		parent::setUp();
		
		$this->conn = $this->createDatabaseConnection();
		
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
	
	protected abstract function createDatabaseConnection();
	
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
		$this->assertEquals(1, $this->conn->lastInsertId(['#__blog', 'id']));
		
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
		$blog = [
			'title' => 'My Foo Blog',
			'created_at' => time()
		];
		
		$this->conn->insert('#__blog', $blog);
		$blog['id'] = $this->conn->lastInsertId(['#__blog', 'id']);
		
		$stmt = $this->conn->prepare("SELECT * FROM `#__blog` WHERE `id` = :id");
		$stmt->bindValue('id', $blog['id']);
		$stmt->execute();
		$this->assertEquals($blog, $stmt->fetchNextRow());
		
		$post = [
			'blog_id' => $blog['id'],
			'title' => 'My first Post',
			'content' => 'Hello World :)',
			'created_at' => time()
		];
		
		$this->conn->insert('#__post', $post);
		$post['id'] = $this->conn->lastInsertId(['#__post', 'id']);
		
		$stmt = $this->conn->prepare("SELECT * FROM `#__post` WHERE `id` = :id");
		$stmt->bindValue('id', $post['id']);
		$stmt->execute();
		$this->assertEquals($post, $stmt->fetchNextRow());
		
		$tag = [
			'name' => 'Test'
		];
		
		$this->conn->insert('#__tag', $tag);
		$tag['id'] = $this->conn->lastInsertId(['#__tag', 'id']);
		
		$stmt = $this->conn->prepare("SELECT * FROM `#__tag` WHERE `id` = :id");
		$stmt->bindValue('id', $tag['id']);
		$stmt->execute();
		$this->assertEquals($tag, $stmt->fetchNextRow());
		
		$stmt = $this->conn->prepare("SELECT COUNT(*) FROM `#__post_tag`");
		$stmt->execute();
		$this->assertEquals(0, $stmt->fetchNextColumn(0));
		
		$this->conn->upsert('#__post_tag', [
			'post_id' => $post['id'],
			'tag_id' => $tag['id']
		], []);
		
		$stmt = $this->conn->prepare("SELECT COUNT(*) FROM `#__post_tag`");
		$stmt->execute();
		$this->assertEquals(1, $stmt->fetchNextColumn(0));
		
		$this->conn->upsert('#__post_tag', [
			'post_id' => $post['id'],
			'tag_id' => $tag['id']
		], []);
		
		$stmt = $this->conn->prepare("SELECT COUNT(*) FROM `#__post_tag`");
		$stmt->execute();
		$this->assertEquals(1, $stmt->fetchNextColumn(0));
		
		$this->assertEquals(1, $this->conn->delete('#__post_tag', [
			'post_id' => $post['id'],
			'tag_id' => $tag['id']
		]));
		
		$stmt = $this->conn->prepare("SELECT COUNT(*) FROM `#__post_tag`");
		$stmt->execute();
		$this->assertEquals(0, $stmt->fetchNextColumn(0));
	}
	
	public function testCanFetchNextColumn()
	{
		$this->conn->insert('#__blog', ['title' => 'Test 2', 'created_at' => time()]);
		$this->conn->insert('#__blog', ['title' => 'Test 1', 'created_at' => time()]);
		
		$stmt = $this->conn->prepare("SELECT `title` FROM `#__blog` ORDER BY `title`");
		$this->assertFalse($stmt->fetchNextColumn(0));
		
		$stmt->execute();
		$this->assertEquals('Test 1', $stmt->fetchNextColumn(0));
		$this->assertEquals('Test 2', $stmt->fetchNextColumn('title'));
		$this->assertFalse($stmt->fetchNextColumn('title'));
	}
	
	public function testCanFetchNextEnhancedColumn()
	{
		$this->conn->insert('#__blog', ['title' => 'Test 1', 'created_at' => time()]);
	
		$stmt = $this->conn->prepare("SELECT `title` FROM `#__blog` ORDER BY `title`");
		$stmt->transform('title', function($title) { return strtoupper($title); });
		$stmt->compute('result', function(array $row) { return 'RESULT: ' . $row['title']; });
		
		$stmt->execute();
		$this->assertEquals('RESULT: TEST 1', $stmt->fetchNextColumn('result'));
	}
	
	public function provideFetchStyles()
	{
		return [
			[DB::FETCH_ASSOC],
			[DB::FETCH_BOTH],
			[DB::FETCH_NUM]
		];
	}
	
	/**
	 * @dataProvider provideFetchStyles
	 */
	public function testCannotFetchBeyondEndOfResultSet($fetchStyle)
	{
		$blog = [
			'title' => 'My Foo Blog',
			'created_at' => time()
		];
		
		$this->conn->insert('#__blog', $blog);
		
		$stmt = $this->conn->prepare("SELECT * FROM `#__blog`");
		$this->assertFalse($stmt->fetchNextRow($fetchStyle));
		
		$stmt->execute();
		$this->assertTrue(is_array($stmt->fetchNextRow($fetchStyle)));
		$this->assertFalse($stmt->fetchNextRow($fetchStyle));
	}
	
	/**
	 * @dataProvider provideFetchStyles
	 */
	public function testIteratorWillNotFetchBeyondEndOfResultSet($fetchStyle)
	{
		$blog = [
			'title' => 'My Foo Blog',
			'created_at' => time()
		];
		
		$this->conn->insert('#__blog', $blog);
		
		$stmt = $this->conn->prepare("SELECT * FROM `#__blog`");
		$this->assertCount(0, iterator_to_array($stmt->fetchRowsIterator($fetchStyle)));
		
		$stmt->execute();
		$this->assertCount(1, iterator_to_array($stmt->fetchRowsIterator($fetchStyle)));
	}
	
	public function testCanFetchColumnsByAlias()
	{
		$this->conn->insert('#__blog', ['title' => 'Test 2', 'created_at' => time()]);
		$this->conn->insert('#__blog', ['title' => 'Test 1', 'created_at' => time()]);
		
		$stmt = $this->conn->prepare("SELECT `title` FROM `#__blog` ORDER BY `title`");
		$this->assertEquals([], $stmt->fetchColumns('title'));
		
		$stmt->execute();
		$this->assertEquals(['Test 1', 'Test 2'], $stmt->fetchColumns('title'));
	}
	
	public function testCanFetchColumnsByNumber()
	{
		$this->conn->insert('#__blog', ['title' => 'Test 2', 'created_at' => time()]);
		$this->conn->insert('#__blog', ['title' => 'Test 1', 'created_at' => time()]);
	
		$stmt = $this->conn->prepare("SELECT `title` FROM `#__blog` ORDER BY `title`");
		$this->assertEquals([], $stmt->fetchColumns(0));
	
		$stmt->execute();
		$this->assertEquals(['Test 1', 'Test 2'], $stmt->fetchColumns(0));
	}
	
	public function testCanFetchTransformedColumns()
	{
		$this->conn->insert('#__blog', ['title' => 'Test 2', 'created_at' => time()]);
		$this->conn->insert('#__blog', ['title' => 'Test 1', 'created_at' => time()]);
	
		$stmt = $this->conn->prepare("SELECT `title` FROM `#__blog` ORDER BY `title`");
		$stmt->transform('title', function($title) { return strtoupper($title); });
		$this->assertEquals([], $stmt->fetchColumns('title'));
	
		$stmt->execute();
		$this->assertEquals(['TEST 1', 'TEST 2'], $stmt->fetchColumns('title'));
	}
	
	public function testCanFetchComputedColumns()
	{
		$this->conn->insert('#__blog', ['title' => 'Test 2', 'created_at' => time()]);
		$this->conn->insert('#__blog', ['title' => 'Test 12', 'created_at' => time()]);
	
		$stmt = $this->conn->prepare("SELECT `title` FROM `#__blog` ORDER BY `title`");
		$stmt->compute('length', function(array $row) { return strlen($row['title']); });
		$this->assertEquals([], $stmt->fetchColumns('length'));
	
		$stmt->execute();
		$this->assertEquals([7, 6], $stmt->fetchColumns('length'));
	}
	
	public function provideMapKeyAndValue()
	{
		return [
			['id', 'title'],
			[0, 1],
			['id', 1],
			[0, 'title']
		];
	}
	
	/**
	 * @dataProvider provideMapKeyAndValue
	 */
	public function testCanFetchMap($key, $value)
	{
		$this->conn->insert('#__blog', ['title' => 'Test 1', 'created_at' => time()]);
		$this->conn->insert('#__blog', ['title' => 'Test 2', 'created_at' => time()]);
		
		$stmt = $this->conn->prepare("SELECT `id`, `title` FROM `#__blog` ORDER BY `title`");
		$this->assertEquals([], $stmt->fetchMap($key, $value));
		
		$stmt->execute();
		$this->assertEquals([1 => 'Test 1', 2 => 'Test 2'], $stmt->fetchMap($key, $value));
	}
	
	public function testCanFetchMapWithEnhancedRowData()
	{
		$this->conn->insert('#__blog', ['title' => 'Test 1', 'created_at' => time()]);
		$this->conn->insert('#__blog', ['title' => 'Test 2', 'created_at' => time()]);
		
		$stmt = $this->conn->prepare("SELECT `id`, `title` FROM `#__blog` ORDER BY `title`");
		$stmt->transform('id', function($id) { return $id + 10; });
		$stmt->transform('title', function($title) { return strtoupper($title); });
		
		$stmt->execute();
		$this->assertEquals([11 => 'TEST 1', 12 => 'TEST 2'], $stmt->fetchMap('id', 'title'));
	}
}
