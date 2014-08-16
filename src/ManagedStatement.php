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

/**
 * Managed statements attach the connection to the current managed transaction.
 * 
 * @author Martin Schröder
 */
class ManagedStatement extends PreparedStatement
{
	protected function __construct(ManagedConnection $conn, array $paramEncoders = [])
	{
		parent::__construct($conn, $paramEncoders);
	}
	
	public function execute($params = NULL)
	{
		$manager = $this->conn->getTransactionManager();
		
		if($manager->inTransaction())
		{
			$manager->getTransaction()->attachResource($this->conn);
		}
		
		return parent::execute($params);
	}
}
