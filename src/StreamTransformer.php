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

use KoolKode\Stream\GzipInputStream;
use KoolKode\Stream\ResourceStream;
use KoolKode\Stream\StreamInterface;
use KoolKode\Stream\StringStream;

/**
 * Transforms a column in DB result into an input stream.
 * 
 * @author Martin Schröder
 */
class StreamTransformer
{
	/**
	 * DB input stream gzip-compressed?
	 * 
	 * @var boolean
	 */
	protected $compressed;

	/**
	 * Create a new DB input stream transformer.
	 * 
	 * @param string $compressed DB contents gzip-compressed?
	 */
	public function __construct($compressed = false)
	{
		$this->compressed = $compressed ? true : false;
	}
	
	/**
	 * Transforms the given value into an input stream.
	 * 
	 * @param mixed $value
	 * @return StreamInterface or NULL when column value is NULL.
	 */
	public function __invoke($value)
	{
		if($value === NULL)
		{
			return NULL;
		}
		
		if(is_resource($value))
		{
			$stream = new ResourceStream($value);
		}
		else
		{
			$stream = new StringStream((string)$value);
		}
		
		return $this->compressed ? new GzipInputStream($stream) : $stream;
	}
}
