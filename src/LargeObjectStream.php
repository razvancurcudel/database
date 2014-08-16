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

use KoolKode\Stream\ResourceStream;
use KoolKode\Stream\StreamInterface;

/**
 * Manages allocation and processing of database-specific LOB params.
 * 
 * @author Martin Schröder
 */
class LargeObjectStream extends ResourceStream
{
	public function __construct($resource)
	{
		if(is_resource($resource))
		{
			parent::__construct($resource);
		}
		elseif($resource instanceof ResourceStream)
		{
			parent::__construct($resource->getResource());
		}
		elseif($resource instanceof StreamInterface)
		{
			parent::__construct(fopen((string)$resource, 'rb'));
		}
		elseif(preg_match("'^/|(?:[^:\\\\/]+://)|(?:[a-z]:[\\\\/])'i", $resource))
		{
			parent::__construct(fopen((string)$resource, 'rb'));
		}
		else
		{
			$fp = @fopen('php://temp', 'rb+');
			
			if($fp === false)
			{
				throw new \RuntimeException('Unable to open temp stream');
			}
			
			fwrite($fp, (string)$resource);
			rewind($fp);
			
			parent::__construct($fp);
		}
	}
}
