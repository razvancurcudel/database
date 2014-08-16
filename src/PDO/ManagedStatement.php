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

/**
 * Database statement that auto-attaches the DB connection to a transaction manager when neccessary.
 * 
 * @author Martin Schröder
 */
class ManagedStatement extends Statement
{
	/**
	 * Create database statement using a managed connection.
	 * 
	 * @param ManagedConnection $conn
	 * @param \PDOStatement $stmt
	 */
	public function __construct(ManagedConnection $conn, \PDOStatement $stmt)
	{
		parent::__construct($conn, $stmt);
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function execute()
	{
		$manager = $this->conn->getTransactionManager();
	
		if($manager->inTransaction())
		{
			$manager->getTransaction()->attachResource($this->conn);
		}
	
		return parent::execute();
	}
}
