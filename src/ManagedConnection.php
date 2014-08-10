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

use KoolKode\Transaction\TransactionInterface;
use KoolKode\Transaction\TransactionManagerInterface;
use KoolKode\Transaction\TransactionResourceInterface;

/**
 * Managed statements are connected to an external transaction manager.
 * 
 * @author Martin Schröder
 */
class ManagedConnection extends Connection implements TransactionResourceInterface
{
	protected $manager;
	
	public function __construct(TransactionManagerInterface $manager, $dsn, $username = NULL, $password = NULL, array $options = [])
	{
		parent::__construct($dsn, $username, $password, $options);
		
		$this->manager = $manager;
	}
	
	public static function getStatementClass()
	{
		return ManagedStatement::class;
	}
	
	public function getTransactionManager()
	{
		return $this->manager;
	}
	
	public function inTransaction()
	{
		return $this->manager->inTransaction();
	}
	
	public function beginTransaction()
	{
		$transaction = $this->manager->beginTransaction();
		$transaction->attachResource($this);
	}
	
	public function commit()
	{
		$this->manager->commit();
	}
	
	public function rollBack()
	{
		$this->manager->rollBack();
	}
	
	public function beginManagedTransaction(TransactionInterface $transaction)
	{
		$this->performBeginTransaction(($this->transLevel == 0) ? NULL : $transaction->getIdentifier());
		
		$this->transLevel++;
	}
	
	public function commitManagedTransaction(TransactionInterface $transaction)
	{
		$this->transLevel--;
		
		if($this->transLevel == 0)
		{
			return $this->performCommit();
		}
		
		$this->performBeginTransaction($transaction->getParent()->getIdentifier());
	}
	
	public function rollBackManagedTransaction(TransactionInterface $transaction)
	{
		$this->transLevel--;
		
		if($this->transLevel == 0)
		{
			return $this->performRollBack();
		}
		
		$this->performRollBack($transaction->getIdentifier());
	}
	
	public function exec($sql)
	{
		if($this->manager->inTransaction())
		{
			$this->manager->getTransaction()->attachResource($this);
		}
		
		return parent::exec($sql);
	}
	
	public function query($sql, $fetchType = NULL, $arg1 = NULL, $arg2 = NULL)
	{
		if($this->manager->inTransaction())
		{
			$this->manager->getTransaction()->attachResource($this);
		}
		
		return parent::query($sql, $fetchType, $arg1, $arg2);
	}
}
