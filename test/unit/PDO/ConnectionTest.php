<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\PDO;

use KoolKode\Database\BaseConnectionTest;
use KoolKode\Database\ConnectionManager;
use KoolKode\Database\PrefixConnectionDecorator;

class ConnectionTest extends BaseConnectionTest
{
	protected $conn;
	
	protected function createDatabaseConnection()
	{
		$dsn = (string)self::getEnvParam('DB_DSN', 'sqlite::memory:');
		$username = self::getEnvParam('DB_USERNAME', NULL);
		$password = self::getEnvParam('DB_PASSWORD', NULL);
		
		$manager = new ConnectionManager();
		$manager->setEventDispatcher($this->eventDispatcher);
		
		$conn = $manager->createPDOConnection($dsn, $username, $password);
		$conn->addDecorator(new PrefixConnectionDecorator('db_'));
		
		return $conn;
	}
}
