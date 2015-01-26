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
 * Creates a variable-size list of params to be bound to a prepared statement.
 * 
 * @author Martin Schröder
 */
class PlaceholderList implements \Countable, \IteratorAggregate
{
	protected $params;
	
	protected $prefix;
	
	public function __construct(array $params, $paramPrefix = NULL)
	{
		if(empty($params))
		{
			throw new \InvalidArgumentException('Placholder lists must not be empty');
		}
		
		$this->params = $params;
		$this->prefix = ($paramPrefix === NULL) ? NULL : (string)$paramPrefix;
	}
	
	public function count()
	{
		return count($this->params);
	}
	
	public function getIterator()
	{
		return new \ArrayIterator($this->params);
	}
	
	public function __toString()
	{
		$sql = '';
		$i = 0;
		
		foreach(array_keys($this->params) as $k)
		{
			if($i++ != 0)
			{
				$sql .= ', ';
			}
			
			if($this->prefix === NULL)
			{
				$sql .= is_integer($k) ? '?' : ':' . $k;
			}
			else
			{
				$sql .= ':' . $this->prefix . $k; 
			}
		}
		
		return $sql;
	}
	
	public function getParams()
	{
		return $this->params;
	}
	
	public function getBindParams()
	{
		if($this->prefix === NULL)
		{
			return $this->params;
		}
		
		$params = [];
		
		foreach($this->params as $k => $v)
		{
			$params[$this->prefix . $k] = $v;
		}
		
		return $params;
	}
}
