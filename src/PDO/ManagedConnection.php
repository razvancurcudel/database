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

use KoolKode\Transaction\TransactionInterface;
use KoolKode\Transaction\TransactionManagerInterface;
use KoolKode\Transaction\TransactionResourceInterface;

/**
 * Database connection that utilizes an external transaction manager.
 * 
 * @author Martin Schröder
 */
class ManagedConnection extends Connection implements TransactionResourceInterface
{
	/**
	 * The active transaction manager.
	 * 
	 * @var TransactionManagerInterface
	 */
	protected $manager;
	
	/**
	 * Create a PDO-based connection with an external transaction manager.
	 * 
	 * @param TransactionManagerInterface $manager
	 * @param \PDO $pdo
	 * @param string $tablePrefix
	 */
	public function __construct(TransactionManagerInterface $manager, \PDO $pdo, $tablePrefix = '')
	{
		parent::__construct($pdo, $tablePrefix);
		
		$this->manager = $manager;
	}
	
	/**
	 * Get the transaction manager.
	 * 
	 * @return TransactionManagerInterface
	 */
	public function getTransactionManager()
	{
		return $this->manager;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function inTransaction()
	{
		return $this->manager->inTransaction();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function beginTransaction()
	{
		$transaction = $this->manager->beginTransaction();
		$transaction->attachResource($this);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function commit()
	{
		$this->manager->commit();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function rollBack()
	{
		$this->manager->rollBack();
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function beginManagedTransaction(TransactionInterface $transaction)
	{
		$this->performBeginTransaction(($this->transLevel == 0) ? NULL : $transaction->getIdentifier());
	
		$this->transLevel++;
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function commitManagedTransaction(TransactionInterface $transaction)
	{
		$this->transLevel--;
	
		if($this->transLevel == 0)
		{
			return $this->performCommit();
		}
	
		$this->performBeginTransaction($transaction->getParent()->getIdentifier());
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function rollBackManagedTransaction(TransactionInterface $transaction)
	{
		$this->transLevel--;
	
		if($this->transLevel == 0)
		{
			return $this->performRollBack();
		}
	
		$this->performRollBack($transaction->getIdentifier());
	}
}
