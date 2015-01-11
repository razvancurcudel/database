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

use KoolKode\Stream\StreamInterface;
use KoolKode\Util\UUID;

/**
 * Transforms a column in a DB result row into a UUID object.
 * 
 * @author Martin Schröder
 */
class UUIDTransformer
{
	/**
	 * Transform column value into an UUID.
	 * 
	 * @param string $value
	 * @return UUID or NULL when the column value is NULL.
	 */
	public function __invoke($value)
	{
		if($value === NULL)
		{
			return NULL;
		}
		
		if($value instanceof StreamInterface)
		{
			return new UUID($value->getContents());
		}
		
		if(is_resource($value))
		{
			return new UUID(stream_get_contents($value));
		}
		
		return new UUID($value);
	}
}
