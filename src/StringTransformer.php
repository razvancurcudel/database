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
 * Transforms a DB column value into a string.
 * 
 * @author Martin Schröder
 */
class StringTransformer
{
	/**
	 * Transforms the given value into a string.
	 * 
	 * @param mixed $value
	 * @return string or NULL when DB column value is NULL.
	 */
	public function __invoke($value)
	{
		if($value === NULL)
		{
			return NULL;
		}
		
		if(is_resource($value))
		{
			return stream_get_contents($value);
		}
		
		return (string)$value;
	}
}
