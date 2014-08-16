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
	public function bindValue($param, $value);
	
	public function bindAll(array $params);
	
	public function bindList(array $params);
	
	public function setLimit($limit);
	
	public function setOffset($offset);
	
	public function execute();
	
	public function transform($column, callable $transformation);
	
	public function compute($column, callable $callback);
	
	public function fetchNextRow();
	
	public function fetchNextColumn($index = 0);
	
	public function fetchRows();
	
	public function fetchColumns();
	
	public function fetchMap($key = 0, $value = 1);
}
