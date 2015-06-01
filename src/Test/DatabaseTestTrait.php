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

use KoolKode\Database\ConnectionInterface;
use KoolKode\Database\ConnectionManager;
use KoolKode\Database\Migration\MigrationManager;
use KoolKode\Database\PrefixConnectionDecorator;
use KoolKode\Event\EventDispatcherInterface;

/**
 * Mixin for DB tests creating connections from env variables.
 * 
 * @author Martin Schröder
 */
trait DatabaseTestTrait
{
	/**
	 * Create a PDO-based DB connection.
	 * 
	 * @param string $prefix
	 * @param EventDispatcherInterface $dispatcher
	 * @return ConnectionInterface
	 */
	protected static function createConnection($prefix = NULL, EventDispatcherInterface $dispatcher = NULL)
	{
		$dsn = (string)static::getEnvParam('DB_DSN', 'sqlite::memory:');
		$username = static::getEnvParam('DB_USERNAME', NULL);
		$password = static::getEnvParam('DB_PASSWORD', NULL);
		
		$manager = new ConnectionManager();
		$manager->setEventDispatcher($dispatcher);
		
		$conn = $manager->createPDOConnection($dsn, $username, $password);
		
		if($prefix !== NULL)
		{
			$conn->addDecorator(new PrefixConnectionDecorator($prefix));
		}
		
		return $conn;
	}
	
	/**
	 * Get an ENV param.
	 * 
	 * @param string $name Name of the env variable.
	 * @param mixed $default Default value to be used.
	 * @return mixed
	 * 
	 * @throws \OutOfBoundsException When env param is not set and no default value was given.
	 */
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
	
	/**
	 * Execute Up-Migrations from the given directory flushing the DB as needed.
	 * 
	 * @param ConnectionInterface $conn
	 * @param string $dir
	 */
	protected static function migrateDirectoryUp(ConnectionInterface $conn, $dir)
	{
		$migrator = new MigrationManager($conn, new \DateTime('@' . $_SERVER['REQUEST_TIME']));
		$migrator->migrateDirectoryUp($dir, true);
	}
}
