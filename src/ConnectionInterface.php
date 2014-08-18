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
	/**
	 * Get the database driver name, one of DB::DRIVER_*.
	 * 
	 * @return string
	 */
	public function getDriverName();
	
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
	
	/**
	 * Execute the given SQL query and return the number of affected rows (only available when
	 * executing INSERT, UPDATE, DELETE and similar statements).
	 * 
	 * @param string $sql
	 * @return integer
	 */
	public function execute($sql);
	
	/**
	 * Create a prepared statement from the given SQL query.
	 * 
	 * @param string $sql
	 * @return StatementInterface
	 */
	public function prepare($sql);
	
	/**
	 * Get the last inserted ID value from an auto-increment column or a named sequence.
	 * 
	 * @param string $sequenceName
	 * @return integer
	 */
	public function lastInsertId($sequenceName = NULL);
	
	/**
	 * Quote the given name to be used as DB-specific object identifier.
	 * 
	 * @param string $identifier
	 * @return string
	 */
	public function quoteIdentifier($identifier);
}
