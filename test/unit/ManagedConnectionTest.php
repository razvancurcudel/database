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

use KoolKode\Database\Test\DatabaseTestCase;
use KoolKode\Transaction\TransactionInterface;
use KoolKode\Transaction\TransactionManager;

class ManagedConnectionTest extends DatabaseTestCase
{
	protected $logger;
	
	protected $manager;
	
	protected function setUp()
	{
		parent::setUp();

		$this->manager = new TransactionManager();
	}
	
	protected function createConnection()
	{
		return new ManagedConnection($this->manager, 'sqlite::memory:');
	}
	
	public function testCanCommitTransactionWithoutResources()
	{
		$this->assertFalse($this->manager->inTransaction());
		
		$this->manager->beginTransaction();
		$this->assertTrue($this->manager->inTransaction());
		
		$trans = $this->manager->getTransaction();
		$this->assertTrue($trans instanceof TransactionInterface);
		$this->assertTrue($trans->isRoot());
		$this->assertNull($trans->getParent());
		$this->assertEquals(0, $trans->getResourceCount());
		
		$this->manager->rollBack();
		$this->assertFalse($this->manager->inTransaction());
	}
	
	public function testCanRollBackTransactionWithoutResources()
	{
		$this->assertFalse($this->manager->inTransaction());
	
		$this->manager->beginTransaction();
		$this->assertTrue($this->manager->inTransaction());
	
		$trans = $this->manager->getTransaction();
		$this->assertTrue($trans instanceof TransactionInterface);
		$this->assertTrue($trans->isRoot());
		$this->assertNull($trans->getParent());
		$this->assertEquals(0, $trans->getResourceCount());
	
		$this->manager->rollBack();
		$this->assertFalse($this->manager->inTransaction());
	}
	
	public function testCanCreateNestedTransactionsWithoutResources()
	{
		$trans1 = $this->manager->beginTransaction();
		$this->assertSame($trans1, $this->manager->getTransaction());
		$this->assertTrue($this->manager->inTransaction());
		
		$trans2 = $this->manager->beginTransaction();
		$this->assertSame($trans2, $this->manager->getTransaction());
		$this->assertTrue($this->manager->inTransaction());
		
		$this->manager->rollBack();
		$this->assertSame($trans1, $this->manager->getTransaction());
		$this->assertTrue($this->manager->inTransaction());
		
		$this->manager->commit();
		$this->assertFalse($this->manager->inTransaction());
	}
	
	public function testManagedConnectionAttachesItselfToTransaction()
	{
		$conn = $this->createConnection();
		
		$conn->exec('CREATE TABLE messages (message TEXT)');
		$conn->exec('INSERT INTO messages VALUES ("foo")');
		
		$stmt = $conn->prepare("SELECT * FROM messages");
		$this->assertTrue($stmt instanceof ManagedStatement);
		
		$trans = $this->manager->beginTransaction();
		$this->assertTrue($conn->inTransaction()); // Due to check with managaer...
		$this->assertFalse($trans->hasResource($conn));
		
		$stmt->execute();
		$this->assertTrue($trans->hasResource($conn));
	}
	
	public function testTransactionManagerIsTriggeredByManagedConnection()
	{
		$conn = $this->createConnection();
		
		$conn->beginTransaction();
		$this->assertTrue($conn->inTransaction());
		$this->assertTrue($this->manager->inTransaction());
		
		$trans = $this->manager->getTransaction();
		$this->assertTrue($trans instanceof TransactionInterface);
		$this->assertTrue($trans->isRoot());
		$this->assertNull($trans->getParent());
		$this->assertEquals(1, $trans->getResourceCount());
		$this->assertTrue($trans->hasResource($conn));
		
		$conn->commit();
		$this->assertFalse($conn->inTransaction());
		$this->assertFalse($this->manager->inTransaction());
	}
	
	public function testNestedTransactionCanBeRolledBackUsingSavepoints()
	{
		$conn1 = $this->createConnection();
		$conn2 = $this->createConnection();
		
		$conn2->exec('CREATE TABLE messages (message TEXT)');
		$conn2->exec('INSERT INTO messages VALUES ("foo")');
		
		$stmt = $conn2->prepare("SELECT * FROM messages");
		
		$trans1 = $this->manager->beginTransaction();
		$trans1->attachResource($conn1);
		$trans1->attachResource($conn2);
		$this->assertTrue($conn1->inTransaction());
		$this->assertTrue($conn2->inTransaction());
		
		$conn2->exec('INSERT INTO messages VALUES ("bar")');
		
		$trans2 = $this->manager->beginTransaction();
		$trans2->attachResource($conn2);
		$this->assertTrue($conn1->inTransaction());
		$this->assertTrue($conn2->inTransaction());
		
		$conn2->exec('INSERT INTO messages VALUES ("baz")');
		
		$stmt->execute();
		$this->assertEquals(['foo', 'bar', 'baz'], $stmt->fetchAll(\PDO::FETCH_COLUMN));
		
		$this->manager->rollBack();
		$this->assertTrue($conn1->inTransaction());
		$this->assertTrue($conn2->inTransaction());
		
		$stmt->execute();
		$this->assertEquals(['foo', 'bar'], $stmt->fetchAll(\PDO::FETCH_COLUMN));
		
		$this->manager->rollBack();
		$this->assertFalse($conn1->inTransaction());
		$this->assertFalse($conn2->inTransaction());
		
		$stmt->execute();
		$this->assertEquals(['foo'], $stmt->fetchAll(\PDO::FETCH_COLUMN));
	}
	
	public function testCommitedNestedTransactionIsNotRolledBack()
	{
		$conn = $this->createConnection();
		
		$conn->exec('CREATE TABLE messages (message TEXT)');
		$conn->exec('INSERT INTO messages VALUES ("foo")');
		
		$stmt = $conn->prepare("SELECT * FROM messages");
		
		$this->assertFalse($this->manager->inTransaction());
		$this->assertFalse($conn->inTransaction());
		
		$trans1 = $this->manager->beginTransaction();
		$trans1->attachResource($conn);
		
		$this->assertTrue($this->manager->inTransaction());
		$this->assertTrue($conn->inTransaction());
		
		$trans2 = $this->manager->beginTransaction();
		$trans2->attachResource($conn);
		
		$this->assertTrue($this->manager->inTransaction());
		$this->assertTrue($conn->inTransaction());
		
		$conn->exec('INSERT INTO messages VALUES ("bar")');
		
		$stmt->execute();
		$this->assertEquals(['foo', 'bar'], $stmt->fetchAll(\PDO::FETCH_COLUMN));
		
		$this->manager->commit();
		
		$this->assertTrue($this->manager->inTransaction());
		$this->assertTrue($conn->inTransaction());
		
		$trans3 = $this->manager->beginTransaction();
		$trans3->attachResource($conn);
		
		$this->assertTrue($this->manager->inTransaction());
		$this->assertTrue($conn->inTransaction());
		
		$conn->exec('INSERT INTO messages VALUES ("baz")');
		
		$stmt->execute();
		$this->assertEquals(['foo', 'bar', 'baz'], $stmt->fetchAll(\PDO::FETCH_COLUMN));
		
		$this->manager->rollBack();
		
		$this->assertTrue($this->manager->inTransaction());
		$this->assertTrue($conn->inTransaction());
		
		$stmt->execute();
		$this->assertEquals(['foo', 'bar'], $stmt->fetchAll(\PDO::FETCH_COLUMN));
		
		$this->manager->commit();
		$this->assertFalse($this->manager->inTransaction());
		$this->assertFalse($conn->inTransaction());
		
		$stmt->execute();
		$this->assertEquals(['foo', 'bar'], $stmt->fetchAll(\PDO::FETCH_COLUMN));
	}
}
