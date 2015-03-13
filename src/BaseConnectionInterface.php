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
 * Contract for base operation that can be performed using a DB connection.
 * 
 * @author Martin Schröder
 */
interface BaseConnectionInterface
{
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
	 * Simplified insert using the given table and key => value pairs as column names / values.
	 *
	 * @param string $tableName
	 * @param array<string, mixed> $values
	*/
	public function insert($tableName, array $values);
	
	/**
	 * Simplified upsert (INSERT when no primary / unique key blocks or UPDATE when a matching row is present) using
	 * the given table and key => value pairs as column names / values.
	 *
	 * @param string $tableName
	 * @param array<string, mixed> $key
	 * @param array<string, mixed> $values
	*/
	public function upsert($tableName, array $key, array $values);
	
	/**
	 * Update rows using key => value pairs when they match the given index constraints.
	 *
	 * @param string $tableName
	 * @param array<string, mixed> $key
	 * @param array<string, mixed> $values
	 * @return integer The number of affected rows.
	*/
	public function update($tableName, array $key, array $values);
	
	/**
	 * Delete all rows that match the given index constraints.
	 *
	 * @param string $tableName
	 * @param array<string, mixed> $key
	 * @return integer The number of deleted rows.
	*/
	public function delete($tableName, array $key);
	
	/**
	 * Get the last inserted ID value from an auto-increment column or a named sequence.
	 *
	 * @param mixed $sequenceName Name of a sequence or an array containing table name and column name of a SERIAL column.
	 * @return integer
	*/
	public function lastInsertId($sequenceName);
	
	/**
	 * Quote the given value for safe use in a query.
	 *
	 * WARNING: Never use this method when you could use param placeholders instead!
	 *
	 * @param mixed $value
	 * @return mixed
	*/
	public function quote($value);
	
	/**
	 * Quote the given name to be used as DB-specific object identifier.
	 *
	 * @param string $identifier
	 * @return string
	*/
	public function quoteIdentifier($identifier);
	
	/**
	 * Will expand the schema object prefix "#__" in the given string input to the actual prefix being used.
	 *
	 * @param string $value
	 * @return string
	*/
	public function applyPrefix($value);
}
