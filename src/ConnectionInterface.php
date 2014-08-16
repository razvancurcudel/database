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
 * Contract for a database connection that might by decorated.
 * 
 * @author Martin Schröder
 */
interface ConnectionInterface
{
	public function inTransaction();

	public function beginTransaction();
	
	public function commit();
	
	public function rollBack();
	
	public function prepare($sql);
	
	public function quoteIdentifier($identifier);
}
