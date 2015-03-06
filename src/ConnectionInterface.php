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

use KoolKode\Database\Platform\AbstractPlatform;

/**
 * Contract for a DB connection that can be used to query a database.
 * 
 * @author Martin Schröder
 */
interface ConnectionInterface extends BaseConnectionInterface
{
	/**
	 * Adds a connection decorator to the DB connection.
	 * 
	 * HINT: Decorators will be cloned before use, make sure they are cloneable.
	 * 
	 * @param ConnectionDecorator $decorator
	 */
	public function addDecorator(ConnectionDecorator $decorator);
	
	/**
	 * Remove a specific decorator instance from the DB connection.
	 * 
	 * @param ConnectionDecorator $decorator
	 */
	public function removeDecorator(ConnectionDecorator $decorator);
	
	/**
	 * Get the database driver name, one of DB::DRIVER_*.
	 * 
	 * @return string
	 */
	public function getDriverName();
	
	/**
	 * Get the underlying DB platform.
	 * 
	 * @return AbstractPlatform
	 */
	public function getPlatform();
	
	/**
	 * Check if the given option is available on the connection.
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function hasOption($name);
	
	/**
	 * Get the value of the given option.
	 *
	 * @param string $name Name of th option.
	 * @param mixed $default Default value to be used
	 * @return mixed
	 *
	 * @throws \OutOfBoundsException When the requested option is not available and no default value is given.
	 */
	public function getOption($name);
	
	/**
	 * Check if a transaction is active.
	 * 
	 * @return boolean
	 */
	public function inTransaction();

	/**
	 * Begin a new transaction, will utilize nested transactions when they are supported.
	 * 
	 * @return ConnectionInterface
	 */
	public function beginTransaction();
	
	/**
	 * Commit the current transaction.
	 * 
	 * @return ConnectionInterface
	 */
	public function commit();
	
	/**
	 * Roll back all changes being performed within the current transaction.
	 * 
	 * @return ConnectionInterface
	 */
	public function rollBack();
}
