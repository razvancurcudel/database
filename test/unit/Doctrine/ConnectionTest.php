<?php

/*
 * This file is part of KoolKode Database.
 *
 * (c) Martin Schröder <m.schroeder2007@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KoolKode\Database\Doctrine;

use Doctrine\DBAL\DriverManager;
use KoolKode\Database\BaseConnectionTest;
use KoolKode\Database\PrefixConnectionDecorator;

class ConnectionTest extends BaseConnectionTest
{
	protected $conn;
	
	protected function createDatabaseConnection()
	{
		$dsn = (string)self::getEnvParam('DB_DSN', 'sqlite::memory:');
		$username = self::getEnvParam('DB_USERNAME', NULL);
		$password = self::getEnvParam('DB_PASSWORD', NULL);
		
		list($type, $params) = explode(':', $dsn, 2);
		
		$options = [
			'driver' => 'pdo_' . $type
		];
		
		if('sqlite::memory:' == $dsn)
		{
			$options['memory'] = true;
		}
		else
		{
			$options['user'] = $username;
			$options['password'] = $password;
			$options['charset'] = 'utf8';
			
			foreach(explode(';', $params) as $conf)
			{
				$parts = array_map('trim', explode('=', $conf, 2));
				$options[$parts[0]] = $parts[1];
			}
		}
		
		$conn = new PrefixConnectionDecorator(new Connection(DriverManager::getConnection($options)), 'db_');
		
		return $conn;
	}
}
