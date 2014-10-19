<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Test;

/**
 * Base class for database tests, provides access to ENV params.
 * 
 * @author Martin Schröder
 */
abstract class DatabaseTestCase extends \PHPUnit_Framework_TestCase
{
	protected function setUp()
	{
		date_default_timezone_set('UTC');
		
		parent::setUp();
	}
	
	protected static function getEnvParam($name)
	{
		if(array_key_exists($name, $GLOBALS))
		{
			return $GLOBALS[$name];
		}
	
		if(array_key_exists($name, $_ENV))
		{
			return $_ENV[$name];
		}
	
		if(array_key_exists($name, $_SERVER))
		{
			return $_SERVER[$name];
		}
	
		if(func_num_args() > 1)
		{
			return func_get_arg(1);
		}
	
		throw new \OutOfBoundsException(sprintf('ENV param not found: "%s"', $name));
	}
}
