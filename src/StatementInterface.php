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
 * Contract for a prepared database query statement.
 * 
 * @author Martin Schröder
 */
interface StatementInterface
{
	/**
	 * Bind the given placeholder value to the statement.
	 * 
	 * @param mixed $param 0-indexed colum number or alias name.
	 * @param mixed $value Value to be bound to the statement.
	 * @return StatementInterface
	 */
	public function bindValue($param, $value);
	
	/**
	 * Bind an array of params to the statement.
	 * 
	 * @param array $params Bind params using array index as param name.
	 * @return StatementInterface
	 */
	public function bindAll(array $params);
	
	/**
	 * Bind params from the given placeholder list to the statement.
	 * 
	 * @param PlaceholderList $list
	 * @return StatementInterface
	 */
	public function bindList(PlaceholderList $list);
	
	/**
	 * Limit the maximum number of rows being returned by the statement.
	 * 
	 * @param integer $limit
	 * @return StatementInterface
	 */
	public function setLimit($limit);
	
	/**
	 * Skip the given number of rows when processing the query result (work will be pushed to the
	 * DB server in order to reduce the amount of rows being sent over the wire).
	 * 
	 * @param integer $limit
	 * @return StatementInterface
	 */
	public function setOffset($offset);
	
	/**
	 * Execute the prepared statement and return the number of affected rows (the row count will only
	 * be computed when executing statements like INSERT, UPDATE, DELETE, it cannot be used with
	 * SELECT queries!).
	 * 
	 * @return integer Number of rows affected by the executed statement.
	 */
	public function execute();
	
	/**
	 * Close the database cursor processing the query result, this allows for other queries to be executed
	 * by the database connection.
	 * 
	 * @return StatementInterface
	 */
	public function closeCursor();
	
	/**
	 * Register a param encoder with this statement.
	 * 
	 * @param ParamEncoderInterface $encoder
	 * @return StatementInterface
	 */
	public function registerParamEncoder(ParamEncoderInterface $encoder);
	
	/**
	 * Transforms a column value using a custom transformation.
	 * 
	 * @param mixed $column
	 * @param callable $transformation
	 * @return StatementInterface
	 */
	public function transform($column, callable $transformation);
	
	/**
	 * Adds a colum to result rows that is being computed from other row columns.
	 * 
	 * @param mixed $column
	 * @param callable $callback
	 * @return StatementInterface
	 */
	public function compute($column, callable $callback);
	
	/**
	 * Fetches the next row from the query result.
	 * 
	 * @param mixed $style Fetch style, one of the DB::FETCH_* constants.
	 * @return array or false when no more rows are available.
	 */
	public function fetchNextRow($style = NULL);
	
	/**
	 * Fetches the next value of the specified colum from the query result.
	 * 
	 * @param mixed $column 0-indexed colum number or alias name.
	 * @return mixed Value of the column or false when no more rows are available.
	 */
	public function fetchNextColumn($column);
	
	/**
	 * Fetch all remaining rows from the query result.
	 * 
	 * @param mixed $style Fetch style, one of the DB::FETCH_* constants.
	 * @return array<array> Array of all remaining rows (may be empty when no more rows are available).
	 */
	public function fetchRows($style = NULL);
	
	/**
	 * Fetch all remaining values of the specified column from the query result.
	 * 
	 * @param mixed $column 0-indexed colum number or alias name.
	 * @return array Values of the speocified colum (may be empty when no more rows are available).
	 */
	public function fetchColumns($column);
	
	/**
	 * Fetch all remaining key-value pairs from the query reuslt into a map.
	 * 
	 * @param mixed $key 0-indexed colum number or alias name of the column being used to populate the key.
	 * @param mixed $value 0-indexed colum number or alias name of the column being used to populate the value.
	 * @return array Map being populated from fetched rows (may be empty when no more rows are available).
	 */
	public function fetchMap($key, $value);
}
