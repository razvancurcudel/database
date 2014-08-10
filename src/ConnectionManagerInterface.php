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
 * Manages database connections.
 * 
 * @author Martin Schröder
 */
interface ConnectionManagerInterface
{
	/**
	 * Get a database connection by configured name.
	 * 
	 * @param string $name Name of the connection as configured.
	 * @return Connection
	 */
	public function getConnection($name);
}
