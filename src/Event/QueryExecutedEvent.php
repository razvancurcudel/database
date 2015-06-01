<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
 
namespace KoolKode\Database\Event;

/**
 * Is triggered whenever a SQL query has been executed successfully.
 * 
 * @author Martin Schröder
 */
class QueryExecutedEvent
{
	/**
	 * SQL of the query (without transformation needed by LIMIT / OFFSET).
	 * 
	 * @var string
	 */
	public $sql;
	
	/**
	 * Params bound to the DB statement.
	 * 
	 * @var array
	 */
	public $params;
	
	/**
	 * Maximum number of rows to be returned.
	 * 
	 * @var integer
	 */
	public $limit;
	
	/**
	 * Number of rows to be skipped.
	 * 
	 * @var integer
	 */
	public $offset;
	
	/**
	 * Execution time of the query (in seconds).
	 * 
	 * @var float
	 */
	public $time;
	
	public function __construct($sql, array $params = [], $limit = 0, $offset = 0, $time = 0)
	{
		$this->sql = (string)$sql;
		$this->params = $params;
		$this->limit = (int)$limit;
		$this->offset = (int)$offset;
		$this->time = (float)$time;
	}
}
